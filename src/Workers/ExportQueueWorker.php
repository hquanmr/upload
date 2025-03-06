<?php
namespace Upload\Workers;

use Upload\Logic\ExportLogic;

class ExportQueueWorker extends BaseQueueWorker
{
    public function __construct()
    {
        parent::__construct();
        $this->queueName = 'export_tasks';
        $this->processorClass = ExportLogic::class;
    }
}