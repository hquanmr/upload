<?php
namespace Upload\Services;

use Workerman\Worker;
use Upload\Workers\ImportQueueWorker; // 明确引入 Worker 类

class QueueServer {
    private $workers = [];

    public function __construct() {
        $this->workers = [
            new ImportQueueWorker() // 直接实例化 Worker
        ];
    }

    public function startAll() {
        foreach ($this->workers as $worker) {
            if ($worker instanceof Worker) {
                $worker->listen();
            }
        }
    }
}
