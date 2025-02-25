<?php

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Log;

use Upload\Services\QueueWorker;
use Upload\Services\HttpServer;
use Upload\Services\WsServer;

define('APP_ROOT', __DIR__ . '/');

/**
 * 批量加载配置文件
 * @param string $configPath 配置文件目录路径
 * @return array 配置信息数组
 */
function loadConfigs($configPath = 'src/Config/')
{
    $configs = [];
    $configFiles = glob($configPath . '*.php');

    foreach ($configFiles as $file) {
        $filename = basename($file, '.php');
        $configs[$filename] = require $file;
    }

    return $configs;
}

 global $SysConfigs;
// 加载所有配置文件
 $SysConfigs = loadConfigs();

try {
    Log::init($configs['log']);
    Db::setConfig($configs['database']);
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
