<?php

namespace Upload\Services;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

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
    }

    public function onWorkerStart()
    {

        // 启动心跳定时器
        $this->startHeartbeat();
        $redis = $this->redis;
        // 定时从进度队列中获取消息
        Timer::add(1, function () use ($redis) {
            while ($progressData = $redis->rPop('progress_queue')) {
                $progress = json_decode($progressData, true);
                if (isset($this->taskConnections[$progress['taskId']])) {
                    $this->taskConnections[$progress['taskId']]->send(json_encode($progress));
                }
            }
        });
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
            $this->redis = connect($host, $port);

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

        // 存储连接和任务ID的映射
        $this->taskConnections[$message['taskId']] = $connection;
    }
}
