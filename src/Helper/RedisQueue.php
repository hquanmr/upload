<?php

namespace Upload\Helper;

use Workerman\RedisQueue\Client;

class RedisQueue
{
    private $client;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->initializeClient();
    }

    private function initializeClient()
    {
        $this->client = new Client('redis://' . $this->config['host'] . ':' . $this->config['port']);
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