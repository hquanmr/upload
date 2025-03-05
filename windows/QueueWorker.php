<?php
require __DIR__ . '/vendor/autoload.php';
// 定义当前目录常量
define('APP_ROOT', __DIR__.'/');


use Upload\Services\QueueWorker;
use think\facade\Db;
use think\facade\Log;
// 加载数据库配置
$logConfig = require 'src/Config/log.php';
$dbConfig = require 'src/Config/database.php';


Log::init($logConfig);
Db::setConfig($dbConfig);

$queue = new QueueWorker();
$queue->count = 4;

Workerman\Worker::runAll();
