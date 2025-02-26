<?php

namespace Upload\Controller;

use Upload\Helper\FileUploader;
use Upload\Helper\RedisQueue;
use Upload\Model\UploadRecords;
use Workerman\Protocols\Http\Request;

class UploadController extends BaseController
{
    private $fileUploader;
    private $redisQueue;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->fileUploader = new FileUploader();
        $this->redisQueue = new RedisQueue();
    }

    public function upload()
    {
        try {
            $file = $this->request->file('excelFile');
            $userId = $this->request->post('userId');
            $goodsId = $this->request->post('goodsId');

            if (!$file) {
                throw new \Exception('No file uploaded', 400);
            }

            $taskId = uniqid();
            $fileName = $file['name'];

            // 验证文件
            $this->fileUploader->validateFile($file);
            // 保存文件
            $savePath = $this->fileUploader->saveUploadedFile($file, $taskId);
            if (!$savePath) {
                throw new \Exception('File save failed', 500);
            }
            // 创建任务数据
            $taskData = $this->redisQueue->createTaskData($taskId, $savePath, $fileName, [
                'userId' => $userId,
                'goodsId' => $goodsId,
            ]);

            // 发送到Redis队列
  
            $this->redisQueue->send('excel_tasks', $taskData);
            // 记录上传信息
            UploadRecords::create([
                'task_id' => $taskId,
                'file_name' => $fileName,
                'download_url' => $savePath,
            ]);

            return [200, 'success', ['taskId' => $taskId]];
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
}
