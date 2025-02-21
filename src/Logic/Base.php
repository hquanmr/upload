<?php

namespace Upload\Logic;


use Redis;

class Base
{
    public $taskId;
    public  $redis;
    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->redis =get_redis_instance();;
    }

 
}