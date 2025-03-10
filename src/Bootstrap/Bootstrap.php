<?php


namespace Upload\Bootstrap;

use think\facade\Log;
use Upload\Helper\Configs;

class Bootstrap {
    public static function init() {
        // 初始化配置
        Configs::init();
        Log::init(Configs::get('log'));

        // 注册自定义错误处理器
        register_shutdown_function([self::class, 'handleFatalError']);
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleFatalError() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            Log::error("Fatal Error: " . json_encode($error));
        }
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        Log::error("Error [$errno]: $errstr in $errfile on line $errline");
    }

    public static function handleException($exception) {
        Log::error("Uncaught Exception: " . $exception->getMessage());
    }
}