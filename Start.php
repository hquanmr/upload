<?php

require __DIR__ . '/vendor/autoload.php';
use think\facade\Db;
use think\facade\Log;

use Upload\Services\QueueWorker;
use Upload\Services\HttpServer;
use Upload\Services\WsServer;

define('APP_ROOT', __DIR__.'/');

// 加载数据库配置
$logConfig = require 'src/Config/log.php';
$dbConfig = require 'src/Config/database.php';


$ws_worker = new WsServer('websocket://0.0.0.0:2346');
$ws_worker->name = 'WebSocketWorker';
$ws_worker->count = 2;
// 允许跨域
$ws_worker->onWebSocketConnect = function($connection, $http_header) {
    $connection->send('WebSocket connected');
};



$queue = new QueueWorker();
$queue->count = 4;


// 启动各服务
$http = new HttpServer('http://0.0.0.0:2345');
$http->name = 'HttpWorker';
$http->count = 4;
$http->saveDir =  __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
Workerman\Worker::runAll();
