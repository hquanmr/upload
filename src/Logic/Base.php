<?php

namespace Upload\Logic;


use Redis;

class Base
{
    private $taskId;
    private $redis;
    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->connectRedis();
    }

    private function connectRedis()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
}