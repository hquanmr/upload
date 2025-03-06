<?php

namespace Upload\Model\Repositories;

use Redis;

class Base
{
    public $taskId;
    public $redis;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->redis = get_redis_instance();
    }
}