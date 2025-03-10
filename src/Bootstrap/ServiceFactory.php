<?php

// 服务工厂类：ServiceFactory.php
namespace Upload\Bootstrap;

use Workerman\Worker;
use Upload\Services\HttpServer;
use Upload\Services\WsServer;

class ServiceFactory {
    public static function createWebSocketServer() {
        $ws_worker = new WsServer('websocket://0.0.0.0:2346');
        $ws_worker->name = 'WebSocketWorker';
        $ws_worker->count = 1;
        $ws_worker->onWebSocketConnect = function ($connection) {
            $connection->send('WebSocket connected');
        };
        return $ws_worker;
    }

    public static function createHttpServer() {
        $http = new HttpServer('http://0.0.0.0:2345');
        $http->name = 'HttpWorker';
        $http->count = 2;
        return $http;
    }
}