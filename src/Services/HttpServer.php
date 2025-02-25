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
            // 基础请求验证
            $this->validateRequest($request);
            
            // 验证和清理请求参数
            $path = $this->sanitizePath($request->path());

            // 获取控制器和方法
            list($controllerClass, $methodName) = $this->getControllerAndMethod($path);

            // 创建控制器实例并调用方法
            $instance = new $controllerClass($request);
            list($code, $msg, $data) = $instance->$methodName();

            // 发送响应
            $this->sendResponse($connection, $code, $msg, $data);
        } catch (\Exception $e) {
            $this->handleError($connection, $e);
        }
    }

    private function validateRequest(Request $request)
    {
        // 检查请求方法
        $allowedMethods = ['GET', 'POST'];
        if (!in_array($request->method(), $allowedMethods)) {
            throw new \Exception('Method Not Allowed', 405);
        }

        // 检查Content-Type
        if ($request->method() === 'POST') {
            $contentType = $request->header('content-type');
            $path = $request->path();
            
            // 文件上传接口允许multipart/form-data
            if (strpos($path, '/upload') === 0) {
                if (!$contentType || strpos($contentType, 'multipart/form-data') === false) {
                    throw new \Exception('Invalid Content-Type for file upload', 415);
                }
            } else {
                // 其他POST接口要求application/json
                if (!$contentType || strpos($contentType, 'application/json') === false) {
                    throw new \Exception('Invalid Content-Type', 415);
                }
            }
        }
    }

    private function sendResponse(TcpConnection $connection, $code, $msg, $data = null)
    {
        send_json($connection, $code, $msg, $data);
    }

    private function handleError(TcpConnection $connection, \Exception $e)
    {
        $code = $e->getCode() ?: 500;
        $message = $e->getMessage() ?: 'Internal Server Error';
        
        // 记录错误日志
        write_Log(sprintf(
            "[%s] %s in %s:%s\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        send_json($connection, $code, $message);
        $connection->close();
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

