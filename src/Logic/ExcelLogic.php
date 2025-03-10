<?php

// ExcelLogic.php
namespace Upload\Logic;

use Upload\Model\Repositories\Saver;
use Upload\Helper\Excel;
use Upload\Helper\Configs;

class ExcelLogic extends BaseLogic
{
    private $databaseSaver;
    private $excelConfig;

    public function __construct($taskId)
    {
        parent::__construct($taskId);
        $this->excelConfig = Configs::get('excel');
        $this->databaseSaver = new Saver($taskId);
    }

    public function handle($taskData)
    {
        $filePath = $taskData['data']['filePath'];
        $extData = $taskData['data']['extData'];
    
        if (!file_exists($filePath)) {
            throw new \Exception('文件不存在');
        }
    
        try {
            // 初始化任务状态
            $this->databaseSaver->event(Saver::PROCESSING);
    
            // 分批读取 Excel 数据
            $excelData = (new Excel())->importExcel($filePath, 0);
            if (empty($excelData)) {
                throw new \Exception('Excel 文件为空');
            }
    
            // 获取总行数并初始化进度
            $this->totalRows = count($excelData);
            if ($this->totalRows === 0) {
                throw new \Exception('Excel 文件没有有效数据');
            }
    
            // 初始化进度，确保 total 不为 0
            $this->updateProgress(0, $this->totalRows, 'processing');
    
            $goodsItemArr = Excel::formattingCells($excelData, $this->excelConfig);
            unset($excelData); // 释放内存
    
            $validRows = $failRows = 0;
            $batchSize = 10;
            $this->currentRow = (int)$this->redis->hGet("tasks:{$this->taskId}", 'currentRow') ?: 0;
            $processedRows = 0; // 新增：实际已处理行数计数器
    
            foreach (array_chunk($goodsItemArr, $batchSize) as $batchIndex => $batch) {
                $batchStartRow = $this->currentRow + ($batchIndex * $batchSize);
                $batchTotal = count($batch);

                foreach ($batch as $row => $rowData) {
                    $currentRow = $batchStartRow + $row;
                    if ($currentRow < $this->currentRow) {
                        continue;
                    }
    
                    try {
                        if ($this->databaseSaver->saveToDB($rowData, $extData)) {
                            $validRows++;
                        } else {
                            $failRows++;
                        }
                    } catch (\Exception $e) {
                        $failRows++;
                        // 记录错误但继续处理
                        write_Log("Row {$currentRow} processing error: " . $e->getMessage());
                    }
                }
    
                // 整批处理完成后统一更新进度
                $processedRows += $batchTotal;
                $this->updateProgress(
                    $processedRows,
                    $this->totalRows,
                    ($processedRows >= $this->totalRows) ? 'completed' : 'processing'
                );
    
                // 定期释放内存
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
    
            $this->updateProgress($this->totalRows, $this->totalRows, 'completed');
            $this->finalizeProgress();
            $this->databaseSaver->event(Saver::COMPLETED);
    
            return [$validRows, $failRows];
        } catch (\Exception $e) {
            $this->failProgress($e->getMessage());
            $this->databaseSaver->event(Saver::FAILED);
            throw $e;
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}
