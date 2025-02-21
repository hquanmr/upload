<?php
namespace Upload\Processors;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Redis;

class ExcelExporter
{
    private $taskId;
    private $redis;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->connectRedis();
    }

    public function handle($query)
    {
        try {
            // 根据查询条件获取数据
            $data = $this->fetchDataFromDB($query);
            $totalRows = count($data);
    
            if ($totalRows === 0) {
                throw new \Exception('没有找到符合条件的数据');
            }
    
            // 创建Excel文件
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
    
            // 设置表头
            $headers = array_keys($data[0]);
            $sheet->fromArray($headers, null, 'A1');
    
            // 写入数据
            $currentRow = 1;
            foreach ($data as $row) {
                $sheet->fromArray(array_values($row), null, 'A'.($currentRow+1));
                $currentRow++;
                $this->updateProgress($currentRow, $totalRows);
            }
    
            // 保存文件
            $savePath = $this->getSavePath();
            $writer = new Xlsx($spreadsheet);
            $writer->save($savePath);
    
            $this->finalizeProgress($savePath);
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        }
    }
    
    private function fetchDataFromDB($query)
    {
        // 构建查询条件
        $sql = "SELECT * FROM your_table WHERE 1=1";
        $params = [];
    
        if (!empty($query['startDate'])) {
            $sql .= " AND create_time >= ?";
            $params[] = $query['startDate'];
        }
    
        if (!empty($query['endDate'])) {
            $sql .= " AND create_time <= ?";
            $params[] = $query['endDate'];
        }
    
        if ($query['status'] !== '') {
            $sql .= " AND status = ?";
            $params[] = $query['status'];
        }
    
        if (!empty($query['keyword'])) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = '%' . $query['keyword'] . '%';
            $params[] = '%' . $query['keyword'] . '%';
        }
    
        // 执行查询
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function updateProgress($current, $total)
    {
        $progress = round(($current / $total) * 100, 2);
        $this->redis->publish("export:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'status' => 'processing'
        ]));
    }

    private function finalizeProgress($filePath)
    {
        $this->redis->publish("export:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'completed',
            'fileUrl' => '/download/' . basename($filePath)
        ]));
    }

    private function notifyError($message)
    {
        $this->redis->publish("export:progress:{$this->taskId}", json_encode([
            'taskId' => $this->taskId,
            'status' => 'failed',
            'error' => $message
        ]));
    }

    private function getSavePath()
    {
        $saveDir = __DIR__ . '/../../exports/';
        if (!is_dir($saveDir)) mkdir($saveDir, 0777, true);
        return $saveDir . $this->taskId . '.xlsx';
    }

    private function connectRedis()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
}