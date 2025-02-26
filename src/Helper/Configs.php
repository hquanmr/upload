<?php

/**
 *  配置类
 */

namespace Upload\Helper;

class Configs {
    private static $config = [];

    /**
     * 获取配置值
     * @param string $key 配置键名，支持点号分隔的多级键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置值
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    public static function set($key, $value) {
        self::$config[$key] = $value;
    }

    /**
     * 初始化配置
     * @param string $configPath 配置文件目录路径
     * @throws \RuntimeException
     */
    public static function init($configPath = 'src/Config/') {
        $configPath = rtrim($configPath, '/') . '/';
        if (!is_dir($configPath)) {
            throw new \RuntimeException("配置目录不存在：{$configPath}");
        }

        $configs = [];
        $configFiles = glob($configPath . '*.php');

        if (empty($configFiles)) {
            throw new \RuntimeException("未找到配置文件：{$configPath}");
        }

        foreach ($configFiles as $file) {
            if (!is_readable($file)) {
                throw new \RuntimeException("配置文件不可读：{$file}");
            }

            $filename = basename($file, '.php');
            $fileConfig = require $file;

            if (!is_array($fileConfig)) {
                throw new \RuntimeException("配置文件格式错误：{$file}");
            }

            $configs[$filename] = $fileConfig;
        }

        self::$config = $configs;
    }
}