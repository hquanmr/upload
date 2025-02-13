<?php

use think\facade\Log;

// 测试用
if (!function_exists('write_Log')) {
    function write_Log($message)
    {
        Log::error($message);
        Log::save();
    }
}

