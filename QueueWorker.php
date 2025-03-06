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
    private $pingInterval = 60; // 心跳间隔，单位：秒
    private $lockKey = 'task_recovery_lock'; // 分布式锁的键
    private $lockTimeout = 60; // 锁的超时时间，单位：秒

    public function __construct()
    {
        parent::__construct();
        $this->count = 4;
        $this->redis = get_redis_instance();
        $this->onWorkerStart = [$this, 'startProcessing'];
    }

    public function startProcessing()
    {
        try {
            // 启动心跳定时器
            $this->startHeartbeat();
            // 恢复未完成的任务
            $pid = posix_getpid();
            var_dump("开始恢复未完成的任务,当前系统进程PID为: " . $pid);
            if ($this->acquireLock()) {
                var_dump("开始恢复未完成的任务, 系统进程PID: {$pid}, Worker ID: {$workerId}");

                $this->recoverTasks();
                $this->releaseLock();
                var_dump("没有抢到锁的进程ID为: " . $pid);
                var_dump("没有抢到锁的进程, 系统进程PID: {$pid}, Worker ID: {$workerId}");
            }
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
                    var_dump("开始处理恢复任务 {$taskId}：" . date('Y-m-d H:i:s'));
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
            // 获取任务元数据
            $taskMeta = $this->redis->hGetAll("tasks:{$taskId}");
            $taskType = $taskMeta['type'] ?? 'import'; // 默认为导入
            
            // 根据类型选择处理器
            $processorClass = ($taskType === 'export') 
                ? \Upload\Processors\ExportProcessor::class 
                : \Upload\Processors\ExcelProcessor::class;
    
            // 更新任务状态
            $this->redis->multi();
            $this->redis->hSet("tasks:{$taskId}", 'status', 'processing');
            $this->redis->zAdd('processing_tasks', time(), $taskId);
            $this->redis->exec();
    
            return new $processorClass($taskId);
        } catch (\Exception $e) {
            write_Log("Error processing task {$taskId}: " . $e->getMessage());
            throw $e;
        }
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
                var_dump("开始处理订阅任务 {$taskId}：" . date('Y-m-d H:i:s'));
                $this->processTask($taskId)->handle($task['filePath'], $task['extData']);
            } catch (\Exception $e) {
                // 记录日志并处理异常
                write_Log("Error processing task {$taskId}: " . $e->getMessage());
            }
        });
    }

    private function acquireLock()
    {
        $lockAcquired = $this->redis->setnx($this->lockKey, time() + $this->lockTimeout);
        if ($lockAcquired) {
            return true;
        }

        $lockExpireTime = $this->redis->get($this->lockKey);
        if ($lockExpireTime < time()) {
            // 锁已过期，尝试获取锁
            $lockAcquired = $this->redis->getSet($this->lockKey, time() + $this->lockTimeout);
            if ($lockAcquired < time()) {
                return true;
            }
        }

        return false;
    }

    private function releaseLock()
    {
        $this->redis->del($this->lockKey);
    }
}
