<?php

namespace Upload\Services;

use Workerman\Worker;
use Workerman\RedisQueue\Client as RedisClient;
use Upload\Processors\ExcelProcessor;
use Upload\Processors\ExcelExporter;
use Workerman\Timer;
use Upload\Helper\Configs;


class QueueWorker extends Worker
{
    protected $redis;
    private $cleanupInterval = 3600; // 清理间隔，单位：秒
    private $pingInterval = 60; // 心跳间隔，单位：秒

    public function __construct()
    {
        parent::__construct();

        $this->redis = get_redis_instance();
        $this->onWorkerStart = [$this, 'startProcessing'];
    }

    public function startProcessing()
    {
        try {
            // 启动定时清理任务
            $this->startCleanupTimer();
            // 启动心跳定时器
            $this->startHeartbeat();
            // 恢复未完成的任务
            $this->recoverTasks();
            // 订阅任务 这里会重新进入订阅
            $this->subscribeTasks();
        } catch (\Exception $e) {
            // 记录日志并处理异常
            write_Log("Error starting processing: " . $e->getMessage());
        }
    }

    private function recoverTasks()
    {

        try {
            // 获取所有进行中未完成的任务
            $Tasks = $this->redis->zRange('processing_tasks', 0, -1);
            if (empty($Tasks)) {
                return;
            }


            foreach ($Tasks as $taskId) {
                $taskData = $this->redis->hMGet("tasks:{$taskId}", ['data', 'status']);



                if ($taskData['status'] === 'processing') {
                    $task = json_decode($taskData['data'], true);


                    $this->processTask($taskId)->handle($task['filePath'], $task['extData']);
                }
            }
        } catch (\Exception $e) {
            // 记录日志并处理异常
            write_Log("Error recovering pending tasks: " . $e->getMessage());
        }
    }

    private function processTask($taskId)
    {


        try {
            // 使用 Redis 事务确保操作的原子性
            $this->redis->multi();
            $this->redis->hSet("tasks:{$taskId}", 'status', 'processing');

            $this->redis->zAdd('processing_tasks', time(), $taskId);
            $this->redis->exec();

            // 启动任务处理器
            $processor = new ExcelProcessor($taskId);
            return $processor;
        } catch (\Exception $e) {
            // 记录日志并处理异常
            write_Log("Error processing task {$taskId}: " . $e->getMessage());
            throw $e;
        }
    }
    private function startCleanupTimer()
    {
        // 每隔 $cleanupInterval 秒执行一次清理
        Timer::add($this->cleanupInterval, function () {
            $this->cleanupCompletedTasks();
        });

        // 立即执行一次清理
        $this->cleanupCompletedTasks();
    }

    private function startHeartbeat()
    {
        Timer::add($this->pingInterval, function () {
            try {
                $this->redis->ping();
                echo "Redis 心跳检测成功\n";
            } catch (\Exception $e) {
                echo "Redis 心跳检测失败：" . $e->getMessage() . "\n";
                $this->reconnectRedis();
            }
        });
    }

    private function reconnectRedis()
    {
        echo "尝试重新连接 Redis...\n";
        try {
            $this->redis->close();
            $host = Configs::get('redis.host', '127.0.0.1');
            $port = Configs::get('redis.port', 6379);
            $this->redis->connect($host, $port);
            $this->subscribeTasks(); // 重新订阅频道
            echo "Redis 重新连接成功\n";
        } catch (\Exception $e) {
            echo "Redis 重新连接失败：" . $e->getMessage() . "\n";
        }
    }

    private function subscribeTasks()
    { 
        $host = Configs::get('redis.host', '127.0.0.1');
        $port = Configs::get('redis.port', 6379);
        $syncRedis = new RedisClient("redis://{$host}:{$port}");
        $syncRedis->subscribe('excel_tasks', function ($taskData) {
            try {

                $task = $taskData['data'];
                $taskId = $taskData['taskId'];
                $taskData['data'] = json_encode($taskData['data']);
      
                $this->redis->hMSet("tasks:{$taskId}", $taskData);
                $this->processTask($taskId)->handle($task['filePath'], $task['extData']);
            } catch (\Exception $e) {
                // 记录日志并处理异常
                write_Log("Error processing task {$taskId}: " . $e->getMessage());
            }
        });
    }
    public function cleanupCompletedTasks()
    {
        try {
            // 清理24小时前完成的任务
            $completedTasks = $this->redis->zRangeByScore('completed_tasks', 0, time() - 86400);
            foreach ($completedTasks as $taskId) {
                $this->redis->del("tasks:{$taskId}");
                $this->redis->zRem('completed_tasks', $taskId);
            }

            // 清理24小时前失败的任务
            $failedTasks = $this->redis->zRangeByScore('failed_tasks', 0, time() - 86400);
            foreach ($failedTasks as $taskId) {
                $this->redis->del("tasks:{$taskId}");
                $this->redis->zRem('failed_tasks', $taskId);
            }

            echo "清理完成：已清理 " . (count($completedTasks) + count($failedTasks)) . " 个任务\n";
        } catch (\Exception $e) {
            echo "清理任务失败：" . $e->getMessage() . "\n";
        }
    }
}
