<?php

use think\facade\Log;
use Workerman\Protocols\Http\Response;
// 测试用
if (!function_exists('write_Log')) {
    function write_Log($message)
    {
        Log::error($message);
        Log::save();
    }
}

if (!function_exists('send_json')) {
    function send_json($conn, $code, $msg, $data = [])
    {
        $httpResponse = new Response(200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST'
        ], json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]));

        $conn->send($httpResponse);
    }
}


if (!function_exists('get_redis_instance')) {
    function get_redis_instance()
    {        
        $redisConfig = $SysConfigs['redis'];

        $redis = new \Redis();
        $redis->connect($redisConfig['host'],  $redisConfig['port']); // 根据实际情况配置 Redis 服务器地址和端口
        return $redis;
    }
}


