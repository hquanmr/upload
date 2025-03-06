<?php
namespace Upload\Logic;
use Upload\Model\Repositories\Saver;
class ExportLogic 
{
    private $taskId;

    public function __construct(string $taskId)
    {
        $this->taskId = $taskId;
    }

    public function handle(string $filePath, array $extData)
    {
        // 具体导出逻辑
        // $exporter = new Saver();
        // $exporter->export($filePath, $this->fetchData($extData));
    }
}