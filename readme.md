## log2ck
This tool can write the monolog standard log directly to clickhouse in real time via the tcp protocol. If you can write regular rules, other standardized log can also support it.

[中文readme](https://github.com/hisune/log2ck/blob/main/readme.zh.md)

### Feature
- Minimalist code
- High performance (compared with online services, the cpu usage is only 1/20 of `filebeat`)
- No dependence on third-party services (such as queues, etc.)
- Configurationalization
- Customization (custom regularization, line processing callback functions)
- Support reading log divided by day
- Support breakpoint resume collection

### Usage specification
1. If you use the default regularity, the log file that needs to be read must be the standard default monolog log format file, and the monolog `name` and `group` name cannot contain spaces.
2. The log to be read must be one line at a time. For example, monolog needs to set the formatter to: `'allowInlineLineBreaks'= > false`

### How to use
```php
composer require "hisune/log2ck"
# vim manager.php
use Hisune\Log2Ck\Manager;
require_once 'vendor/autoload.php';
(new Manager(__DIR__ . DIRECTORY_SEPARATOR . 'config.php'))->run();
# php manager.php
```

### config.php Configuration example
```php
return [
    'env' => [ // System environment variables
//        'bin' => [
//            'php' => '/usr/bin/php', // Optional configuration, the path to which the php bin file belongs
//        ],
        'clickhouse' => [ // Required configuration
            'server' => [
                'host' => '192.168.37.205',
                'port' => '8123',
                'username' => 'default',
                'password' => '',
            ],
            'database' => 'logs', // Database name
            'table' => 'repo', // Table name
        ],
//        'worker' => [
//            'cache_path' => '/dev/shm/', // Optional configuration, worker cache directory
//        ],
//        'logger' => [
//            'enable' => true, // Optional configuration, whether to record logs
//            'path' => __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR, // Specify the directory where the logs are logged, optional configuration, and need to end with /
//        ],
    ],
    'tails' => [
        'access' => [ // Key is the log name, which corresponds to the name of clickhouse
            'repo' => 'api2', // The name of the project to which the log belongs
            'path' => '/mnt/c/access.log', // Log path, fixed file name log
//            'path' => '/mnt/c/access-{date}.log', // Log path, a daily log with a file name, currently only one macro variable {date} is supported. For example, the date format: 2022-02-22
//            'pattern' => '/\[(?P<created_at>.*)\] (?P<logger>\w+).(?P<level>\w+): (?P<message>.*[^ ]+) (?P<context>[^ ]+) (?P<extra>[^ ]+)/', // Optional configuration, if regular processing is not required, set to false
//            'callback' => function($data) { // Optional configuration, this line of data is processed according to a custom callback method, and the content of the method can implement any logic for cleaning this stream by itself.
//                $data['message'] = 'xxoo'; // For example, customize the processing of this data
//                return $data;
//            }
        ],
    ],
];
```

### supervisord
It is recommended to use supervisor to manage your manager process.
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

### clickhouse Log table structure
If you use monolog and use the default regular rules, you can directly use the following table structure. If you have a custom regular, you can customize your own clickhouse table structure based on your own regular matching results.
```clickhouse
create table repo
(
    repo       LowCardinality(String) comment 'Project name',
    name       LowCardinality(String) comment 'Log name',
    host       LowCardinality(String) comment 'The machine where the log is generated',
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
If the content of your message or context is `json`, you can refer to clickhouse's json query function:https://clickhouse.com/docs/en/sql-reference/functions/json-functions/

### TODO
- Further improve write performance: single insert is changed to batch insert
