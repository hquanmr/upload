<?php
namespace Upload\Services;

use Workerman\Worker;
use Workerman\RedisQueue\Client;
use Upload\Processors\ExcelProcessor;
use Upload\Processors\ExcelExporter;

class QueueWorker extends Worker
{
    public function __construct()
    {
        parent::__construct();
        $this->onWorkerStart = [$this, 'startProcessing'];
    }

    public function startProcessing()
    {
        $client = new Client('redis://127.0.0.1:6379');
        
        // 原有导入任务处理
        $client->subscribe('excel_tasks', function($task) {
            $processor = new ExcelProcessor($task['taskId']);
            $processor->handle($task['filePath']);
        });
    
        // 新增导出任务处理
        $client->subscribe('export_tasks', function($task) {
            $exporter = new ExcelExporter($task['taskId']);
            $exporter->handle($task['data']);
        });
    }
}

