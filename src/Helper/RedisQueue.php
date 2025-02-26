<?php

namespace Upload\Helper;

use Workerman\RedisQueue\Client;

class RedisQueue
{
    private $client;

    public function __construct()
    {
        $this->initializeClient();
    }

    private function initializeClient()
    {
        $host = Configs::get('redis.host', '127.0.0.1');
        $port = Configs::get('redis.port', 6379);
        $this->client = new Client("redis://{$host}:{$port}");
    }

    public function send($queue, $data)
    {
        return $this->client->send($queue, $data);
    }

    public function createTaskData($taskId, $savePath, $fileName, $extData = [])
    {
        return [
            'taskId' => $taskId,
            'status' => 'pending',
            'data' => [
                'taskId' => $taskId,
                'filePath' => $savePath,
                'fileName' => $fileName,
                'progress' => 0,
                'createdAt' => time(),
                'extData' => $extData
            ]
        ];
    }
}