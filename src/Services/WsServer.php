<?php

namespace Upload\Services;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Upload\Helper\Configs;

class WsServer extends Worker
{
    protected $taskConnections = []; // 存储任务ID与连接的映射
    protected $redis;

    private $pingInterval = 60; // 心跳间隔，单位：秒

    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->redis = get_redis_instance();
        $this->onMessage = [$this, 'onWsMessage'];
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onClose = [$this, 'onClose'];
    }

    public function onWorkerStart()
    {
        try {
            // 启动心跳定时器
            $this->startHeartbeat();

            // 定时从进度队列中获取消息
            Timer::add(0.5, function () {
                try {
                    // 使用 brPop 处理队列消息，超时时间为 1 秒
                    if ($progressData = $this->redis->brPop('progress_queue', 1)) {
                        list($queueName, $progress) = $progressData;
                        $progress = json_decode($progress, true);
                   
                        if (isset($this->taskConnections[$progress['taskId']]) && $this->taskConnections[$progress['taskId']]->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                      
                            $connection = $this->taskConnections[$progress['taskId']];
                    
                            $connection->send(json_encode($progress));
                        }
                    }
                } catch (\Exception $e) {
                    write_Log("Error processing progress_queue: " . $e->getMessage());
                }
            });
        } catch (\Exception $e) {
            write_Log("Redis 连接失败：" . $e->getMessage());
            $this->reconnectRedis();
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
            echo "Redis 重新连接成功\n";
        } catch (\Exception $e) {
            echo "Redis 重新连接失败：" . $e->getMessage() . "\n";
        }
    }

    public function onWsMessage(TcpConnection $connection, $data)
    {
        $message = json_decode($data, true);
        if (!isset($message['taskId'])) {
            return $connection->send(json_encode(['error' => 'Invalid message format']));
        }

        // 同时存储本地映射用于快速访问
        $this->taskConnections[$message['taskId']] = $connection;
    }

    public function onClose(TcpConnection $connection)
    {
        foreach ($this->taskConnections as $taskId => $conn) {
            if ($conn === $connection) {

                unset($this->taskConnections[$taskId]);
                break;
            }
        }
    }
}
