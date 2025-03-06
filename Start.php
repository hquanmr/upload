<?php

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Log;
use Upload\Helper\Configs;
use Upload\Services\QueueServer;
use Upload\Services\HttpServer;
use Upload\Services\WsServer;


define('APP_ROOT', __DIR__ . '/');

register_shutdown_function('customErrorHandler');

try {
    Configs::init();    // 初始化配置dddd
    Log::init(Configs::get('log'));
    Db::setConfig(Configs::get('database'));


$ws_worker = new WsServer('websocket://0.0.0.0:2346');
$ws_worker->name = 'WebSocketWorker';
$ws_worker->count = 1; //启动多个进程会导致前端连接无法找到对应进程推送，因为每个进程是独立的
// 允许跨域
$ws_worker->onWebSocketConnect = function ($connection, $http_header) {
    $connection->send('WebSocket connected');
};







$http = new HttpServer('http://0.0.0.0:2345');
$http->name = 'HttpWorker';
$http->count = 2;
// 启动各服务

$queue = new QueueServer();
$queue->startAll();

Workerman\Worker::runAll();
} catch (Exception $e) {
    echo ("Failed to start：" . $e->getMessage());
    exit(1);
}
