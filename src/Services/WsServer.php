<?php
namespace Upload\Services;
use \Redis;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class WsServer extends Worker
{
    protected  $redis;

    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->onWorkerStart = [$this, 'initRedis'];
        $this->onMessage = [$this, 'onWsMessage'];
    }

    public function initRedis()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function onWsMessage(TcpConnection $connection, $data)
    {
       
        $message = json_decode($data, true);
        if (!isset($message['taskId'])) return;

        // 订阅任务进度频道
        $taskId = $message['taskId'];
        try {
            $this->redis->subscribe(["excel:progress:$taskId"], function($redis, $channel, $msg) use ($connection) {
                $connection->send($msg);
            });
        } catch (\Throwable $e) {
           var_dump($e->getMessage());
        }

    }
}