## log2ck
此工具能将monolog标准log直接通过tcp协议实时写入clickhouse。如果你会写正则，其他标准化log也能支持。

[English readme](https://github.com/hisune/log2ck/blob/main/readme.md)

### 特性
- 极简代码
- 高性能（在线上业务中对比cpu占用仅为`filebeat`的1/20）
- 无第三方服务依赖（例如队列等）
- 配置化
- 定制化（自定义正则、行处理回调函数）
- 支持读取按天分割的log
- 支持断点续传采集

### 使用规范
1. 如果使用默认正则，则需要待读取的日志文件必须是标准的默认monolog日志格式文件，且monolog的`name`和`group`名称不能包含空格
2. 待读取的日志必须是一行一条，例如monolog需要设置formatter为：`allowInlineLineBreaks' => false`

### 使用方法
```php
composer require "hisune/log2ck"
# vim manager.php
use Hisune\Log2Ck\Manager;
require_once 'vendor/autoload.php';
(new Manager(__DIR__ . DIRECTORY_SEPARATOR . 'config.php'))->run();
# php manager.php
```

### config.php配置举例
```php
return [
    'env' => [ // 系统环境变量
//        'bin' => [
//            'php' => '/usr/bin/php', // 可选配置，php bin文件所属路径
//        ],
        'clickhouse' => [ // 必须的配置
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
//            'cache_path' => '/dev/shm/', // 可选配置，worker缓存目录
//        ],
//        'logger' => [
//            'enable' => true, // 可选配置，是否记录日志
//            'path' => __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR, // 指定记录日志的目录，可选配置，需要以/结尾
//        ],
    ],
    'tails' => [
        'access' => [ // key为日志名称，对应clickhouse的name字段
            'repo' => 'api2', // 日志所属的项目名称
            'path' => '/mnt/c/access.log', // 日志路径，固定文件名日志
//            'host' => 'host1', // 自定义hostname，未设置默认为服务器主机名，对应clickhouse的host字段
//            'path' => '/mnt/c/access-{date}.log', // 日志路径，每日一个文件名的日志，当前只支持{date}一个宏变量，date格式举例：2022-02-22
//            'pattern' => '/\[(?P<created_at>.*)\] (?P<logger>\w+).(?P<level>\w+): (?P<message>.*[^ ]+) (?P<context>[^ ]+) (?P<extra>[^ ]+)/', // 可选配置，如果不需要正则处理，设置为false
//            'callback' => function($data) { // 可选配置，对这行数据按自定义回调方法进行处理，方法内容可以自行实现任何清洗此条流水的逻辑
//                $data['message'] = 'xxoo'; // 举例，自定义处理这个数据
//                return $data;
//            }
//            'clickhouse' => [...] // 也可以对单独的项目配置clickhouse连接信息，配置内容同env的clickhouse数组
        ],
    ],
];
```

### supervisord
推荐使用supervisor管理你的manager进程。
```conf
[program:log2ck]
directory=/data/log2ck
command=php manager.php
user=root
autostart=true
autorestart=true
startretries=10
stderr_logfile=/data/logs/err.log
stdout_logfile=/data/logs/out.log
```

### clickhouse日志表结构
如果使用monolog并且使用的是默认正则，可直接使用下面的表结构，如果是自定义正则，可以根据自己的正则匹配结果自定义自己的clickhouse表结构。
```clickhouse
create table repo
(
    repo       LowCardinality(String) comment '项目名称',
    name       LowCardinality(String) comment '日志名称',
    host       LowCardinality(String) comment '日志产生的机器',
    created_at DateTime,
    logger     LowCardinality(String),
    level      LowCardinality(String),
    message    String,
    context    String,
    extra      String
) engine = MergeTree()
      PARTITION BY toDate(created_at)
      ORDER BY (created_at, repo, host)
      TTL created_at + INTERVAL 10 DAY;
```
如果你的message或context的内容是`json`，可以参考clickhouse的json查询函数：https://clickhouse.com/docs/en/sql-reference/functions/json-functions/

### TODO
- 进一步提升写入性能：单次插入改为批量插入
