<?php

namespace Upload\Services;

use Workerman\Worker;
use Workerman\Timer;


class CleanupWorker  extends Worker
{
    private $redis;
    private $cleanupInterval = 3600; // 清理间隔，单位：秒

    public function __construct()
    {
        parent::__construct();
        $this->redis = get_redis_instance();
        $this->onWorkerStart = [$this, 'startProcessing'];
    }
    public function startProcessing()
    {
        Timer::add($this->cleanupInterval, function () {
            $this->cleanupCompletedTasks();
        });
    }

    private function cleanupCompletedTasks()
    {
        try {
            // 清理24小时前完成的任务
            $completedTasks = $this->redis->zRangeByScore('completed_tasks', 0, time() - 86400);
            foreach ($completedTasks as $taskId) {
                $this->redis->del("tasks:{$taskId}");
                $this->redis->zRem('completed_tasks', $taskId);
            }

            // 清理24小时前失败的任务
            $failedTasks = $this->redis->zRangeByScore('failed_tasks', 0, time() - 86400);
            foreach ($failedTasks as $taskId) {
                $this->redis->del("tasks:{$taskId}");
                $this->redis->zRem('failed_tasks', $taskId);
            }

            echo "清理完成：已清理 " . (count($completedTasks) + count($failedTasks)) . " 个任务\n";
        } catch (\Exception $e) {
            echo "清理任务失败：" . $e->getMessage() . "\n";
        }
    }
}
