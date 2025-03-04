<?php

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Log;
use Upload\Helper\Configs;
use Upload\Services\QueueWorker;
use Upload\Services\HttpServer;
use Upload\Services\WsServer;
use Upload\Services\CleanupWorker;

define('APP_ROOT', __DIR__ . '/');



try {
    Configs::init();    // 初始化配置dddd
    Log::init(Configs::get('log'));
    Db::setConfig(Configs::get('database'));
} catch (Exception $e) {
    echo ("Failed to initialize log or database: " . $e->getMessage());
    exit(1);
}

$ws_worker = new WsServer('websocket://0.0.0.0:2346');
$ws_worker->name = 'WebSocketWorker';
$ws_worker->count = 1; //启动多个进程会导致前端连接无法找到对应进程推送，因为每个进程是独立的
// 允许跨域
$ws_worker->onWebSocketConnect = function ($connection, $http_header) {
    $connection->send('WebSocket connected');
};

$queue = new QueueWorker();

$queue->name = 'QueueWorker';


// 启动清理任务进程
$taskCleanupProcess = new CleanupWorker();
$taskCleanupProcess->count = 1;
$taskCleanupProcess->name = 'CleanupWorker';

$http = new HttpServer('http://0.0.0.0:2345');
$http->name = 'HttpWorker';
$http->count = 2;
// 启动各服务
Workerman\Worker::runAll();
