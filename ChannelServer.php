<?php
require __DIR__ . '/vendor/autoload.php';
// 定义当前目录常量
define('APP_ROOT', __DIR__ . '/');


use Upload\Services\RedisSubscriber;
use think\facade\Db;
use think\facade\Log;
// 加载数据库配置
$logConfig = require 'src/Config/log.php';
$dbConfig = require 'src/Config/database.php';


Log::init($logConfig);
Db::setConfig($dbConfig);
// 启动 Channel 服务
$channel = new Channel\Server('0.0.0.0', 2206);



Workerman\Worker::runAll();
