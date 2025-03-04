<?php

namespace Upload\Processors;

use Upload\Queue\Saver;
use Upload\Helper\Excel;
use Upload\Helper\Configs;

class ExcelProcessor
{
    private $taskId;
    private $redis;
    private $databaseSaver;
    private $excelConfig;
    private $currentRow;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->redis = get_redis_instance();
        $this->excelConfig = Configs::get('excel');
        $this->databaseSaver = new Saver($taskId);
    }

    public function handle($filePath, $extData)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('文件不存在');
        }

        try {
            // 初始化进度
            $this->databaseSaver->event(Saver::PROCESSING);
            $this->updateProgress(0, 0, 'processing');

            // 分批读取Excel数据
            $excelData = (new Excel())->importExcel($filePath, 0);
            if (empty($excelData)) {
                throw new \Exception('Excel文件为空');
            }

            $totalRows = count($excelData);
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
                    $totalRows,
                    ($processedRows >= $totalRows) ? 'completed' : 'processing'
                );

                // 定期释放内存
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            $this->updateProgress($totalRows, $totalRows, 'completed');
            $this->finalizeProgress();
            $this->databaseSaver->event(Saver::COMPLETED);

            return [$validRows, $failRows];
        } catch (\Exception $e) {
            $this->updateProgress(0, 0, 'failed', $e->getMessage());
            $this->databaseSaver->event(Saver::FAILED);
            throw $e;
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    // 修改后的 updateProgress
    private function updateProgress($current, $total, $status = 'processing', $error = null)
    {
        $progress = round(($current / $total) * 100, 2);
        // 保存当前进度到Redis
        $this->redis->hMSet("tasks:{$this->taskId}", [
            'progress' => $progress,
            'currentRow' => $current,
            'status' => $status, // 改为使用传入的状态
            'updatedAt' => time()
        ]);
        $this->redis->rPush('progress_queue', json_encode([
            'taskId' => $this->taskId,
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'status' => $status,
            'error' => $error
        ]));
    }

    // 在ExcelProcessor中标记任务完成
    private function finalizeProgress()
    {
        $this->redis->hSet("tasks:{$this->taskId}", 'status', 'completed');
        $this->redis->zRem('processing_tasks', $this->taskId);
        $this->redis->zAdd('completed_tasks', time(), $this->taskId);
    }
}