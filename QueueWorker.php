<?php
require __DIR__ . '/vendor/autoload.php';
// 定义当前目录常量
define('APP_ROOT', __DIR__);


use Upload\Services\QueueWorker;
use think\facade\Db;
// 加载数据库配置
$config = require 'src/Config/database.php';
Db::setConfig($config);

$queue = new QueueWorker();


Workerman\Worker::runAll();
