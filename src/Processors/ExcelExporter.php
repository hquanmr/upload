<?php
namespace Upload\Processors;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Redis;

class ExcelExporter
{
    private $taskId;
    private $redis;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->connectRedis();
    }

    public function handle($data)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $totalRows = count($data);
            $currentRow = 0;

            // 设置表头
            $sheet->fromArray(array_keys($data[0]), null, 'A1');
            $currentRow++;

            // 写入数据
            foreach ($data as $row) {
                $sheet->fromArray(array_values($row), null, 'A'.($currentRow+1));
                $currentRow++;
                $this->updateProgress($currentRow, $totalRows);
            }

            // 保存文件
            $savePath = $this->getSavePath();
            $writer = new Xlsx($spreadsheet);
            $writer->save($savePath);

            $this->finalizeProgress($savePath);
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        }
    }

    private function updateProgress($current, $total)
    {
        $progress = round(($current / $total) * 100, 2);
        $this->redis->publish("export:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'status' => 'processing'
        ]));
    }

    private function finalizeProgress($filePath)
    {
        $this->redis->publish("export:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'completed',
            'fileUrl' => '/download/' . basename($filePath)
        ]));
    }

    private function notifyError($message)
    {
        $this->redis->publish("export:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'failed',
            'error' => $message
        ]));
    }

    private function getSavePath()
    {
        $saveDir = __DIR__ . '/../../exports/';
        if (!is_dir($saveDir)) mkdir($saveDir, 0777, true);
        return $saveDir . $this->taskId . '.xlsx';
    }

    private function connectRedis()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
}