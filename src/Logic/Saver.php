<?php

namespace Upload\Logic;

use  think\facade\Db;
use Redis;

class DatabaseSaver extends Base
{
  
    public function saveToDB($rowData, $extData)
    {
        $time =     time();
        $user_id =  $extData['user_id'];
        try {
            $map =  $rowData;
            $rowData['create_time'] = $time;

            Db::name('goods_item')->where($map)->find();
            unset($map['goods_id']);
            if (Db::name('goods_item')->where($map)->find()) {

                $this->redis->lpush($this->taskId, json_encode(
                    [
                        'rowData' => $rowData,
                        'status' => 'failed',
                        'error' => '商品已经存在'
                    ]
                ));
                write_Log('商品已经存在');
                return false;
            } else {

                Db::name('goods_item')->data(array_merge($rowData, ['user_id' => $user_id]))->insert();
                return true;
            }
        } catch (\Throwable $e) {
            write_Log($e->getMessage());
            return false;
        }
    }


}
