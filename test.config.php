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
            'server' => [
                'host' => '192.168.37.205',
                'port' => '8123',
                'username' => 'default',
                'password' => '',
            ],
            'database' => 'logs', // 入库名称
            'table' => 'repo', // 入库表
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
        'access1' => [ // key为日志名称，对于clickhouse的name
            'repo' => 'api2', // 日志所属的项目名称
            'path' => '/mnt/c/Users/hi/Downloads/test.log', // 日志路径，当前只支持{date}一个宏变量，格式举例：2022-02-22
//            'pattern' => false,
//            'callback' => function($data) {
//                $data['message'] = 'xxoo'; // 自定义处理这个数据
//                return $data;
//            }
//            'path' => '/data/wwwroot/api2/runtime/logs/access-{date}.log', // 日志路径，当前只支持{date}一个宏变量，格式举例：2022-02-22
        ],
    ],
];