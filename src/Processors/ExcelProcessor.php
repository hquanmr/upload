<?php

namespace Upload\Processors;



use Upload\Logic\Saver;
use Upload\Helper\Excel;

class ExcelProcessor
{
    private $taskId;
    private $redis;
    private $databaseSaver;
    private  $excelConfig;
    private $currentRow;
    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->redis = get_redis_instance();



        $this->excelConfig = require APP_ROOT . '/src/Config/excel.php';
        $this->databaseSaver = new Saver($taskId);
    }

    public function handle($filePath, $extData)
    {
        try {

            $excelData = (new Excel())->importExcel($filePath, 0);

            $totalRows = count($excelData);

            $this->databaseSaver->event(Saver::PROCESSING); // 发布进度更新事件
            $goodsItemArr = Excel::formattingCells($excelData,  $this->excelConfig);

            $validRows = 0;
            $failRows = 0;
       
       
            // 恢复上次处理进度
            $this->currentRow = (int)$this->redis->hGet("tasks:{$this->taskId}", 'currentRow') ?: 0;
         
            foreach ($goodsItemArr as $row => &$rowData) {
                if ($row <= $this->currentRow){
                               continue;
                } else {
                    if ($this->databaseSaver->saveToDB($rowData, $extData)) {
                        $validRows++;
                    } else {
                        $failRows++;
                    }
                    $this->updateProgress($row, $totalRows);

                }
            
          
              
            }

            $this->finalizeProgress($validRows, $failRows);
            $this->databaseSaver->event(Saver::COMPLETED); // 发布进度更新事件
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
            $this->databaseSaver->event(Saver::FAILED); // 发布进度更新事件
        } finally {
            @unlink($filePath);
        }
    }

    private function updateProgress($current, $total)
    {

 

        $progress = round(($current / $total) * 100, 2);
        // 保存当前进度到Redis
        $this->redis->hMSet("tasks:{$this->taskId}", [
            'progress' => $progress,
            'currentRow' => $current,
            'status' => 'processing',
            'updatedAt' => time()
        ]);

        $this->redis->publish("excel:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'status' => 'processing'
        ]));
    }
    // 在ExcelProcessor中标记任务完成
    private function finalizeProgress($validRows, $failRows)
    {
        $this->redis->publish("excel:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'completed',
            'validRows' => $validRows,
            'errorCount' => $failRows
        ]));
        $this->redis->hSet("tasks:{$this->taskId}", 'status', 'completed');
        $this->redis->zRem('processing_tasks', $this->taskId);

        
        $this->redis->zAdd('completed_tasks', time(), $this->taskId);
    }

    private function notifyError($message)
    {
        $this->redis->publish("excel:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'failed',
            'error' => $message
        ]));
    }
}
