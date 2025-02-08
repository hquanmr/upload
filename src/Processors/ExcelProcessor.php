<?php

namespace Upload\Processors;


use Redis;
use Upload\Logic\DatabaseSaver;
use Upload\Helper\Excel;

class ExcelProcessor
{
    private $taskId;
    private $redis;
    private $databaseSaver;
    private  $excelConfig;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->connectRedis();
        $this->excelConfig = require APP_ROOT.'/src/Config/excel.php';
        $this->databaseSaver = new DatabaseSaver();
    }

    public function handle($filePath)
    {
        try {

            $excelData = (new Excel())->importExcel($filePath, 0);

            $totalRows = count($excelData);

            $this->updateProgress(0, $totalRows);
            $goodsItemArr = Excel::formattingCells($excelData,  $this->excelConfig);

            $validRows = 0;
            $failRows = 0;
            foreach ($goodsItemArr as $row => &$rowData) {
                if ($this->databaseSaver->saveToDB($rowData,['user_id'=>1])) {
                    $validRows++;
                } else {
                    $failRows++;
                }

                $this->updateProgress($row, $totalRows);
            }

            $this->finalizeProgress($validRows,$failRows);
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        } finally {
            @unlink($filePath);
        }
    }

    private function updateProgress($current, $total)
    {
        $progress = round(($current / $total) * 100, 2);

        $this->redis->publish("excel:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'status' => 'processing'
        ]));
    }

    private function finalizeProgress($validRows,$failRows)
    {
        $this->redis->publish("excel:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'completed',
            'validRows' => $validRows,
            'errorCount' => $failRows
        ]));
    }

    private function notifyError($message)
    {
        $this->redis->publish("excel:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'failed',
            'error' => $message
        ]));
    }



 

    private function connectRedis()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
}
