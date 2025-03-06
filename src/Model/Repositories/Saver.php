<?php

namespace Upload\Model\Repositories;

use think\facade\Db;
use Redis;

class Saver extends Base
{
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const COMPLETED = 'completed';
    const FAILED = 'failed';

    public function saveToDB($rowData, $extData)
    {
        $time = time();
        $userId = $extData['userId'];
        $goodsId = $extData['goodsId'];

        try {
            $map = $rowData;
            $rowData['create_time'] = $time;
            $goods_item = array_merge($rowData, ['user_id' => $userId, 'goods_id' => $goodsId]);

   
            if (Db::name('goods_item')->where($map)->find()) {
                Db::name('goods_item')->where($map)->update($goods_item);

                $this->redis->lpush('excel:error:' . $this->taskId, json_encode(
                    [
                        'rowData' => $rowData,
                        'status' => 'failed',
                        'error' => '商品已经存在'
                    ]
                ));
                write_Log('商品已经存在');
                return false;
            } else {
                Db::name('goods_item')->data($goods_item)->insert();
                return true;
            }
        } catch (\Throwable $e) {
            write_Log($e->getMessage());
            return false;
        }
    }

    public function event($event)
    {
        Db::name('upload_records')->where(['task_id' => $this->taskId])->update(['status' => $event]);
    }
 
}