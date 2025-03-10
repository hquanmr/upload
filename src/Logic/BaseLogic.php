<?php 
// BaseLogic.php
namespace Upload\Logic;

use Upload\Helper\Configs;

abstract class BaseLogic implements LogicInterface
{
    protected $taskId;
    protected $redis;
    protected $totalRows = 0;
    protected $currentRow = 0;

    public function __construct(string $taskId)
    {
        $this->taskId = $taskId;
        $this->redis = get_redis_instance();
    }

    // 更新任务进度
    protected function updateProgress($current, $total, $status = 'processing', $error = null)
    {
        $progress = $total > 0 ? round(($current / $total) * 100, 2) : 0;

        // 保存当前进度到 Redis
        $this->redis->hMSet("tasks:{$this->taskId}", [
            'progress' => $progress,
            'currentRow' => $current,
            'status' => $status,
            'updatedAt' => time(),
            'error' => $error,
        ]);

        // 将进度推送到队列
        $this->redis->rPush('progress_queue', json_encode([
            'taskId' => $this->taskId,
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'status' => $status,
            'error' => $error,
        ]));
    }

    // 标记任务完成
    protected function finalizeProgress()
    {
        $this->redis->hSet("tasks:{$this->taskId}", 'status', 'completed');
        $this->redis->zRem('processing_tasks', $this->taskId);
        $this->redis->zAdd('completed_tasks', time(), $this->taskId);
    }

    // 标记任务失败
    protected function failProgress($error = null)
    {
        $this->redis->hMSet("tasks:{$this->taskId}", [
            'status' => 'failed',
            'error' => $error,
            'updatedAt' => time(),
        ]);
        $this->redis->zRem('processing_tasks', $this->taskId);
        $this->redis->zAdd('failed_tasks', time(), $this->taskId);
    }
}