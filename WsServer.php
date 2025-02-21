<?php
require __DIR__ . '/vendor/autoload.php';


use Upload\Services\WsServer;

define('APP_ROOT', __DIR__.'/');

$ws_worker = new WsServer('websocket://0.0.0.0:2346');

$ws_worker->name = 'WebSocketWorker';
$ws_worker->count = 1;
// 允许跨域
$ws_worker->onWebSocketConnect = function($connection, $http_header) {
    $connection->send('WebSocket connected');
};
Workerman\Worker::runAll();
