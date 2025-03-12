<?php
namespace Upload\Workers;

use Workerman\Worker;
use Workerman\Timer;
use Workerman\RedisQueue\Client;
use Upload\Helper\Configs;

abstract class BaseQueueWorker extends Worker
{
    protected $redis;
    protected $queueName;  // 由子类定义队列名
    protected $processorClass; // 由子类定义处理器

    private $pingInterval = 60;
    private $lockKeyPrefix = 'queue_lock:';
    private $lockTimeout = 60;

    public function __construct()
    {
        parent::__construct();
        $this->count = 2; // 每个队列默认启动 2 个进程
        $this->redis = get_redis_instance();
        $this->onWorkerStart = [$this, 'startProcessing'];
    }

    public function startProcessing()
    {
        try {
            $this->startHeartbeat();
            $this->recoverPendingTasks();
            $this->subscribeQueue();
        } catch (\Exception $e) {
            write_Log(get_class($this) . " 启动失败: " . $e->getMessage());
        }
    }

    protected function subscribeQueue()
    {
        $client = new Client("redis://" . Configs::get('redis.host') . ":" . Configs::get('redis.port'));
        $client->subscribe($this->queueName, function ($data) {
            $this->handleTask($data);
        });
    }

    protected function handleTask(array $taskData)
    {
        $taskId = $taskData['taskId'];
        try {
            $this->redis->hMSet("tasks:{$taskId}", [
                'type' => $this->queueName,
                'status' => 'processing',
                'data' => json_encode($taskData['data'])
            ]);

            // 使用子类指定的处理器
            $processor = new $this->processorClass($taskId);
            $processor->handle($taskData);

            $this->redis->hSet("tasks:{$taskId}", 'status', 'completed');
        } catch (\Exception $e) {
            $this->redis->hMSet("tasks:{$taskId}", [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function recoverPendingTasks()
    {
        if ($this->acquireLock()) {
            $tasks = $this->redis->zRangeByScore('pending_tasks', 0, time());
            foreach ($tasks as $taskId) {
                $this->handleTask(json_decode($this->redis->get("task:{$taskId}"), true));
            }
            $this->releaseLock();
        }
    }

    private function acquireLock(): bool
    {
        $key = $this->lockKeyPrefix . $this->queueName; 
        return (bool)$this->redis->setnx( $key, time() + $this->lockTimeout);
       
    }

    private function releaseLock()
    {
        $this->redis->del($this->lockKeyPrefix . $this->queueName);
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
            $this->subscribeQueue(); // 重新订阅频道
            echo "Redis 重新连接成功\n";
        } catch (\Exception $e) {
            echo "Redis 重新连接失败：" . $e->getMessage() . "\n";
        }
    }
  
}