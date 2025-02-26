<?php

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Log;
use Upload\Helper\Configs;
use Upload\Services\QueueWorker;
use Upload\Services\HttpServer;
use Upload\Services\WsServer;

define('APP_ROOT', __DIR__ . '/');






try { 
     Configs::init();    // 初始化配置
     $host = Configs::get('redis.host', '127.0.0.1');

    Log::init( Configs::get('log'));
    Db::setConfig(Configs::get('database'));
} catch (Exception $e) {
    echo ("Failed to initialize log or database: " . $e->getMessage());
    exit(1);
}

$ws_worker = new WsServer('websocket://0.0.0.0:2346');
$ws_worker->name = 'WebSocketWorker';
$ws_worker->count = 2;
// 允许跨域
$ws_worker->onWebSocketConnect = function ($connection, $http_header) {
    $connection->send('WebSocket connected');
};

$queue = new QueueWorker();
$queue->count = 4;
$queue->name = 'QueueWorker';

$http = new HttpServer('http://0.0.0.0:2345');
$http->name = 'HttpWorker';
$http->count = 4;
// 启动各服务
Workerman\Worker::runAll();
