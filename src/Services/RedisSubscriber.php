<?php
namespace Upload\Services;

use Workerman\Worker;
use Channel\Client as ChannelClient;
use Workerman\Timer;

class RedisSubscriber extends Worker
{
    public function __construct()
    {
        parent::__construct();
        $this->onWorkerStart = [$this, 'startSubscribing'];
    }

    public function startSubscribing()
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);

        // 订阅任务进度频道
        $redis->subscribe(['excel:progress:*'], function($redis, $channel, $message) {
            // 解析任务ID
            $taskId = substr($channel, strlen('excel:progress:'));

            // 通过 Channel 将消息传递给 WebSocket 进程
            ChannelClient::publish('task.progress', [
                'taskId' => $taskId,
                'data' => json_decode($message, true)
            ]);
        });

        // 添加心跳机制
        $this->addHeartbeat($redis);
    }

    private function addHeartbeat($redis)
    {
        // 每隔60秒发送一次心跳
        Timer::add(60, function() use ($redis) {
            try {
                $redis->ping();
            } catch (\Exception $e) {
                echo "Redis 连接异常：" . $e->getMessage() . "\n";
                $this->reconnectRedis($redis);
            }
        });
    }

    private function reconnectRedis($redis)
    {
        echo "尝试重新连接 Redis...\n";
        try {
            $redis->close();
            $redis->connect('127.0.0.1', 6379);
            $this->startSubscribing(); // 重新订阅
            echo "Redis 重新连接成功\n";
        } catch (\Exception $e) {
            echo "Redis 重新连接失败：" . $e->getMessage() . "\n";
        }
    }
}