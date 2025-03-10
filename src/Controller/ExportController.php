<?php

namespace Upload\Controller;

use Upload\Helper\RedisQueue;
use Workerman\Protocols\Http\Request;

class ExportController extends BaseController
{
   private $redisQueue;

   public function __construct(Request $request)
   {
      parent::__construct($request);

      $this->redisQueue = new RedisQueue();
   }


   public function Export()
   {
      try {

         $userId = $this->request->post('userId');
         $goodsId = $this->request->post('goodsId');


         $taskId = uniqid();
         // 创建任务数据
         $taskData = $this->redisQueue->createTaskData($taskId, $savePath, $fileName, [
            'userId' => $userId,
            'goodsId' => $goodsId,
         ]);

         // 发送到Redis队列

         $this->redisQueue->send('excel_tasks', $taskData);
         // 记录上传信息
         ExportRecords::create([
            'task_id' => $taskId,
            'file_name' => $fileName,
            'download_url' => $savePath,

            'created_at' => time(),
         ]);

         return [200, 'success', ['taskId' => $taskId]];
      } catch (\Exception $e) {
         // 记录异常信息
         write_Log("Error in upload: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

         throw $e; // 异常交给上层统一处理
      }
   }
}
