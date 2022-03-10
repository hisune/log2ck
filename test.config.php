<?php
/**
 * User: hi@hisune.com
 * Date: 2022/02/17/0017
 * Time: 11:43
 */
declare(strict_types=1);
if (php_sapi_name() != 'cli') exit();

return [
    'env' => [ // 系统环境变量
//        'bin' => [
//            'php' => '/usr/bin/php',
//        ],
        'clickhouse' => [
            'dsn' => 'tcp://192.168.37.205:9000',
            'username' => 'default',
            'password' => '',
            'options' => [
                'connect_timeout' => 3,
                'socket_timeout'  => 30,
                'tcp_nodelay'     => true,
                'persistent'      => true,
            ],
            'database' => 'logs', // 入库名称
            'table' => 'repo', // 入库表
            'max_sent_count' => 100, // 单批次数据达到多少条时执行插入
            'max_sent_wait' => 10, // 如果单批数据条数不满足，则最少多少秒执行一次插入
        ],
//        'worker' => [
//            'cache_path' => '/dev/shm/', // worker缓存目录
//        ],
//        'logger' => [
//            'enable' => true, // 是否记录日志
//            'path' => __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR, // 指定记录日志的目录，可选，需要以/结尾
//        ],
    ],
    'tails' => [
        'access1' => [ // key为日志名称，对应clickhouse的name字段
            'repo' => 'api2', // 日志所属的项目名称
            'path' => '/mnt/c/Users/hi/Downloads/test.log', // 日志路径，当前只支持{date}一个宏变量，格式举例：2022-02-22
//            'host' => 'host1', // 自定义hostname，未设置默认为服务器主机名，对应clickhouse的host字段
//            'pattern' => false,
//            'callback' => function($data) {
//                $data['message'] = 'xxoo'; // 自定义处理这个数据
//                return $data;
//            }
//            'path' => '/data/wwwroot/api2/runtime/logs/access-{date}.log', // 日志路径，当前只支持{date}一个宏变量，格式举例：2022-02-22
        ],
    ],
];