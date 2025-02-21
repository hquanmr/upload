<?php

namespace Upload\Services;

use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Connection\TcpConnection;
use Workerman\RedisQueue\Client;
use Upload\Controller\UploadController;
use Upload\Controller\ExportController;
use Upload\Controller\RecordsController;
use Upload\Controller\DownloadController;

class HttpServer extends Worker
{
    protected $controllers = [
        '/export' => [ExportController::class, 'export'],
        '/download' => [DownloadController::class, 'download'],
        '/records' => [RecordsController::class, 'list'],
        '/upload' => [UploadController::class, 'upload']
    ];

    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->onMessage = [$this, 'onRequest'];
    }

    public function onRequest(TcpConnection $connection, Request $request)
    {
        try {
            // if ($request->method() !== 'POST') {
            //     throw new \Exception('Method Not Allowed', 405);
            // }

            // 验证和清理请求参数
            $path = $this->sanitizePath($request->path());

            // 获取控制器和方法
            list($controllerClass, $methodName) = $this->getControllerAndMethod($path);

            // 创建控制器实例并调用方法
            $instance = new $controllerClass( $request);
            list($code, $msg,$data)  = $instance->$methodName();

          
            send_json($connection, $code , $msg,$data);
        } catch (\Exception $e) {
            send_json($connection, $e->getCode() ?: 500, $e->getMessage());
            $connection->close();
        }
    }

    private function sanitizePath($path)
    {
        // 清理路径，防止注入攻击
        return preg_replace('/[^a-zA-Z0-9\/\-_]/', '', $path);
    }

    private function getControllerAndMethod($path)
    {
        foreach ($this->controllers as $route => $handler) {
            if (strpos($path, $route) === 0) {
                return $handler;
            }
        }
        throw new \Exception('Not Found', 404);
    }
}

