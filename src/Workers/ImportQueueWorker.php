<?php
namespace Upload\Workers;

use Upload\Logic\ExcelLogic;


class ImportQueueWorker extends BaseQueueWorker
{
    public function __construct()
    {
        parent::__construct();
        $this->name = 'ImportQueueWorker';
        $this->queueName = 'excel_tasks';
        $this->processorClass = ExcelLogic::class;
    }
}