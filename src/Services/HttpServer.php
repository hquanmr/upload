<?php

namespace Upload\Services;

use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Connection\TcpConnection;
use Workerman\RedisQueue\Client;

class HttpServer extends Worker
{

    public  $saveDir;

    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->onMessage = [$this, 'onRequest'];
    }

    public function onRequest(TcpConnection $connection, Request $request)
    {
        // 处理文件上传
        if ($request->method() !== 'POST') {
            throw new \Exception('Method Not Allowed', 405);
        }
        // 添加导出路由
        if ($request->path() === '/export') {
            $taskId = uniqid();
            $exportData = json_decode($request->rawBody(), true);

            (new Client('redis://127.0.0.1:6379'))->send('export_tasks', [
                'taskId' => $taskId,
                'data' => $exportData
            ]);
        } elseif (strpos($request->path(), '/download/') === 0) {  // 添加下载路由

            $filename = basename($request->path());
            $filePath = __DIR__ . '/../../exports/' . $filename;

            if (file_exists($filePath)) {
                $connection->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                $connection->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                return $connection->send(file_get_contents($filePath));
            }

            return $this->sendJson($connection, 404, 'File not found');
        } else {
            $file = $request->file('excel');

            if (!$file) {
                throw new \Exception('No file uploaded', 400);
            }

            $this->validateFile($file);

            // 生成唯一任务ID
            $taskId = uniqid();

            $savePath = $this->saveUploadedFile($file, $taskId);
            if (!$savePath) {
                return $this->sendJson($connection, 500, 'File save failed');
            }

            // 推送任务到Redis队列
            (new Client('redis://127.0.0.1:6379'))->send('excel_tasks', [
                'taskId' => $taskId,
                'filePath' => $savePath
            ]);
        }



        $this->sendJson($connection, 200, ' success', ['taskId' => $taskId]);
    }

    private function sendJson($conn, $code, $msg, $data = [])
    {
        // 设置响应头和响应体
        $httpResponse = new Response(200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST'
        ], json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]));

        $conn->send($httpResponse);
    }

    private function saveUploadedFile($file, $taskId)
    {
        $saveDir = $this->saveDir;

        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0777, true) && !is_dir($saveDir)) {
                throw new \Exception('Failed to create directory', 500);
            }
        }




        $safeFileName = $this->generateSafeFileName($taskId, pathinfo($file['name'], PATHINFO_EXTENSION));
        $savePath = $saveDir  . $safeFileName;

        return rename($file['tmp_name'], $savePath) ? $savePath : null;
    }

    function generateSafeFileName($taskId, $extension)
    {
        return sprintf('%s.%s', $taskId, strtolower($extension));
    }

    function validateFile($file, $maxSize = 10485760, $allowedTypes = ['xlsx', 'xls'])
    {
        if ($file['size'] > $maxSize) {
            throw new \Exception('File too large');
        }
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), $allowedTypes)) {
            throw new \Exception('Invalid file type');
        }
    }
}
