<?php
require __DIR__ . '/vendor/autoload.php';

use Upload\Services\HttpServer;
use think\facade\Db;
use think\facade\Log;

define('APP_ROOT', __DIR__.'/');
// 加载数据库配置
$logConfig = require APP_ROOT.'src/Config/log.php';
$dbConfig = require APP_ROOT.'src/Config/database.php';



Log::init($logConfig);
Db::setConfig($dbConfig);

// 启动各服务
$http = new HttpServer('http://0.0.0.0:2345');
$http->name = 'HttpWorker';
$http->count = 4;
$http->saveDir =  __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
Workerman\Worker::runAll();
