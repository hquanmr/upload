<?php

namespace Upload\Helper;

use Upload\Helper\RedisQueue;

class FileUploader
{
    private $saveDir;
    private $redisQueue;

    public function __construct( $uploadDir = null)
    {
        $this->saveDir = $uploadDir ?: APP_ROOT . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
  
        $this->initializeUploader();
    }

    private function initializeUploader()
    {
        // 创建上传目录
        if (!is_dir($this->saveDir)) {
            mkdir($this->saveDir, 0755, true);
        }
    }

    public function validateFile($file, $maxSize = 10485760, $allowedTypes = ['xlsx', 'xls'])
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

    public function saveUploadedFile($file, $taskId)
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

 
}