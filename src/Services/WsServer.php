<?php
namespace Upload\Services;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use React\EventLoop\Factory;
use Clue\React\Redis\Factory as RedisFactory;

class WsServer extends Worker
{
    protected $taskConnections = []; // 存储任务ID与连接的映射
    protected $redisClient;
    protected $loop;

    public function __construct($socket)
    {
        parent::__construct($socket);

        $this->onMessage = [$this, 'onWsMessage'];
        $this->onWorkerStart = [$this, 'onWorkerStart'];
    }

    public function onWorkerStart()
    {
        // 创建 ReactPHP 事件循环
        $this->loop = Factory::create();

        // 创建 Redis 客户端
        $redisFactory = new RedisFactory($this->loop);
        $this->redisClient = $redisFactory->createClient('tcp://127.0.0.1:6379');

        // 订阅 Redis 频道
        $this->redisClient->psubscribe(['excel:progress.*'])->then(function ($redis) {
            $redis->on('message', function ($pattern, $channel, $message) {
                $this->handleRedisMessage($channel, $message);
            });
        });

        // 运行事件循环
        $this->loop->run();
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

    protected function handleRedisMessage($channel, $message)
    {
        // 解析频道名称以获取任务ID
        preg_match('/excel:progress\.(\d+)/', $channel, $matches);
        if (isset($matches[1])) {
            $taskId = $matches[1];
            if (isset($this->taskConnections[$taskId])) {
                // 推送消息到相应的 WebSocket 连接
                $this->taskConnections[$taskId]->send($message);
            }
        }
    }
}