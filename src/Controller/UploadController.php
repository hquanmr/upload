<?php

namespace Upload\Controller;


use Workerman\RedisQueue\Client;
use Upload\Model\UploadRecords;
use Workerman\Protocols\Http\Request;

class UploadController extends BaseController
{

    private $saveDir = APP_ROOT . 'public'.DIRECTORY_SEPARATOR.'uploads' . DIRECTORY_SEPARATOR;
    private $redisClient; // 新增 Redis 客户端缓存

    public function __construct(Request $request)
    {
        parent::__construct($request);
        // 初始化 Redis 客户端
        $this->redisClient = new Client('redis://' . $this->redisConfig['host'] . ':' . $this->redisConfig['port']);
        // 提前创建上传目录
        if (!is_dir($this->saveDir)) {
            mkdir($this->saveDir, 0755, true);
        }
    }

    public function upload()
    {
        try {
            $file = $this->request->file('excelFile');
            $userId =  $this->request->post('userId');
            $goodsId = $this->request->post('goodsId');

            if (!$file) {
                throw new \Exception('No file uploaded', 400);
            }

            $taskId = uniqid();
            $fileName = $file['name'];
            $this->validateFile($file);

            $savePath = $this->saveUploadedFile($file, $taskId);
            if (!$savePath) {
                throw new \Exception('File save failed', 500);
            }


            $taskData = [
                'taskId' => $taskId,
                'status' => 'pending',
                'data' => [
                    'taskId' => $taskId,
                    'filePath' => $savePath,
                    'fileName' => $fileName,
                    'progress' => 0,
                    'createdAt' => time(),
                    'extData' => [
                        'userId' => $userId,
                        'goodsId' => $goodsId,
                    ]
                ]


            ];
            $this->sendToRedisQueue('excel_tasks', $taskData);

            UploadRecords::create([
                'task_id' => $taskId,
                'file_name' => $fileName,
                'download_url' => $savePath,
            ]);

            return [200, ' success', ['taskId' => $taskId]];
        } catch (\Exception $e) {
            // 记录异常信息
            write_Log("Error in upload: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            // 显式清理临时文件
            if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
                unlink($file['tmp_name']);
            }
            throw $e; // 异常交给上层统一处理
        }
    }

    private function saveUploadedFile($file, $taskId)
    {
        $safeFileName = $this->generateSafeFileName($taskId, pathinfo($file['name'], PATHINFO_EXTENSION));
        $savePath = $this->saveDir . $safeFileName;

        if (!rename($file['tmp_name'], $savePath)) {
            unlink($file['tmp_name']); // 移动失败时清理临时文件
            return null;
        }

        return $savePath;
    }

    private function generateSafeFileName($taskId, $extension)
    {
        return sprintf('%s.%s', $taskId, strtolower($extension));
    }

    private function validateFile($file, $maxSize = 10485760, $allowedTypes = ['xlsx', 'xls'])
    {
        if ($file['size'] > $maxSize) {
            throw new \Exception('File too large');
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), $allowedTypes)) {
            throw new \Exception('Invalid file type');
        }

        // 验证文件 MIME 类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) {
            throw new \Exception('Invalid file content');
        }
    }

    private function sendToRedisQueue($queue, $data)
    {
        $this->redisClient->send($queue, $data); // 复用客户端实例
    }
}
