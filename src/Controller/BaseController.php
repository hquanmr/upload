<?php

namespace Upload\Controller;
use Workerman\Protocols\Http\Request;


class BaseController
{
    protected $redisConfig;
    protected $request;
    public function __construct( Request $request)
    {   
        $this->request = $request;
        $this->redisConfig = require APP_ROOT . '/src/Config/redis.php';
  
    }

}