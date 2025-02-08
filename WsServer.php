<?php
require __DIR__ . '/vendor/autoload.php';


use Upload\Services\WsServer;



$ws_worker = new WsServer('websocket://0.0.0.0:2346');

$ws_worker->name = 'WebSocketWorker';
$ws_worker->count = 1;
Workerman\Worker::runAll();
