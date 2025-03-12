<?php
// ExportLogic.php
namespace Upload\Logic;

use Upload\Model\Repositories\Saver;

class ExportLogic extends BaseLogic
{
    public function handle(array $taskData)
    {
        try {

            $filePath = $taskData['data']['filePath'];
            $extData = $taskData['data']['extData'];
            // 初始化进度
            $this->updateProgress(0, 0, 'processing');

            // 模拟导出逻辑
            $exporter = new Saver();
            $exporter->export($taskData['filePath'], $this->fetchData($taskData['extData']));

            // 更新进度为完成
            $this->updateProgress(1, 1, 'completed');
            $this->finalizeProgress();

            return ['success' => true];
        } catch (\Exception $e) {
            $this->failProgress($e->getMessage());
            throw $e;
        }
    }

    private function fetchData($extData)
    {
        // 模拟数据获取逻辑
        return ['data' => $extData];
    }
}