<?php
return [
    'default'    =>    'mysql',
    'connections'    =>    [
        'mysql'    =>    [
            // 数据库类型
            'type'        => 'mysql',
            // 服务器地址
            'hostname'    => '127.0.0.1',
            // 数据库名
            'database'    => 'upload',
            // 数据库用户名
            'username'    => 'root',
            // 数据库密码
            'password'    => 'root',
            // 数据库连接端口
            'hostport'    => '3306',
            // 数据库连接参数
            'params'      => [],
            // 数据库编码默认采用utf8
            'charset'     => 'utf8',
            // 数据库表前缀
            'prefix'      => 'hhyp_',
            'auto_timestamp' => true,
            'auto_timestamp' => 'int',
            'break_reconnect'   => true,
            // 断线重连的间隔时间（可选）
            'break_reconnect_time'  => 150, // 默认0秒
        ],
    ],
];