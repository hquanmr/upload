<?php

require __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Upload\Bootstrap\Bootstrap;
use Upload\Bootstrap\ServiceFactory;
use Upload\Services\QueueServer;
use think\facade\Log;


try {
    define('APP_ROOT', __DIR__ . '/');
    Bootstrap::init(); // 初始化应用

    $ws_worker = ServiceFactory::createWebSocketServer();
    $http = ServiceFactory::createHttpServer();

    // 启动各服务
    $queue = new QueueServer();
    $queue->startAll();

    Worker::runAll();
} catch (\Exception $e) {
    Log::error("Application failed to start: " . $e->getMessage());
    Log::error($e->getTraceAsString());
    echo "Application failed to start. Check logs for details.\n";
    exit(1);
}
