<?php
require __DIR__ . '/vendor/autoload.php';

use Upload\Services\HttpServer;

// 启动各服务
$http = new HttpServer('http://0.0.0.0:2345');
$http->name = 'HttpWorker';
$http->count = 4;
$http->saveDir =  __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
Workerman\Worker::runAll();
