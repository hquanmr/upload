<?php

namespace Upload\Logic;

use  think\facade\Db;

class DatabaseSaver
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


                Db::name('goods_item')->where($map)->update(['user_id' => $user_id]);
            } else {

                Db::name('goods_item')->data(array_merge($rowData, ['user_id' => $user_id]))->insert();
            }
        } catch (\Throwable $e) {
            file_put_contents(__DIR__ . '/error.log', $e->getMessage() . "\n", FILE_APPEND);
        }
        return true;
    }
}
