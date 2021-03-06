## log2ck
English | [中文](./readme.zh.md)

[![Latest Stable Version](http://poser.pugx.org/hisune/log2ck/v)](https://packagist.org/packages/hisune/log2ck) [![Total Downloads](http://poser.pugx.org/hisune/log2ck/downloads)](https://packagist.org/packages/hisune/log2ck) [![Latest Unstable Version](http://poser.pugx.org/hisune/log2ck/v/unstable)](https://packagist.org/packages/hisune/log2ck) [![License](http://poser.pugx.org/hisune/log2ck/license)](https://packagist.org/packages/hisune/log2ck) [![PHP Version Require](http://poser.pugx.org/hisune/log2ck/require/php)](https://packagist.org/packages/hisune/log2ck)

This tool can write the monolog standard log directly to clickhouse in real time via the tcp protocol. If you can write regular rules, other standardized log can also support it.

### Feature
- Minimalist code
- High performance (Verify that the cpu usage in online business is only 1/20 of `filebeat`)
- No dependence on third-party services (such as queues, etc.)
- Configurable
- Customization (custom regularization, line processing callback functions)
- Supports reading log divided by day
- Supports automatic breakpoint resume collection
- Supports batch insert data
- Supports graceful restart

### Usage specification
1. If you use the default regularity, the log file that needs to be read must be the standard default monolog log format file, and the monolog `name` and `group` name cannot contain spaces.
2. The log to be read must be one line at a time. For example, monolog needs to set the formatter to: `'allowInlineLineBreaks'= > false`

### How to use
```sh
# Install
composer require "hisune/log2ck"
# Modify config.php to the configuration you want 
cp vendor/hisune/log2ck/test.config.php config.php
# Create manager
vim manager.php
```
Example of the content of the `manager.php` file:
```php
<?php
use Hisune\Log2Ck\Manager;
require_once 'vendor/autoload.php';
(new Manager(__DIR__ . DIRECTORY_SEPARATOR . 'config.php'))->run();
```
```sh
# Begin execution 
php manager.php
```
By default, the manager and worker execution logs can be seen in the `vendor/hisune/log2ck/logs/` directory. You can also modify the storage path of these two logs through the configuration file.

### config.php Configuration example
```php
return [
    'env' => [ // System environment variables
//        'bin' => [
//            'php' => '/usr/bin/php', // Optional configuration, the path to which the php bin file belongs
//        ],
        'clickhouse' => [ // Required configuration
            'dsn' => 'tcp://192.168.37.205:9000',
            'username' => 'default',
            'password' => '',
            'options' => [
                'connect_timeout' => 3,
                'socket_timeout'  => 30,
                'tcp_nodelay'     => true,
                'persistent'      => true,
            ],
            'database' => 'logs', // Database name
            'table' => 'repo', // Table name
            'max_sent_count' => 100, // Insert when there are many pieces of data in a single batch
            'max_sent_wait' => 10, // If the number of data items in a single batch is not satisfied, the insertion will be performed at least once in how many seconds
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
        'access' => [ // Key is the log name, corresponding to the name field of clickhouse
            'repo' => 'api2', // The name of the project to which the log belongs
            'path' => '/mnt/c/access.log', // eg: Log path, fixed file name log
//            'path' => '/mnt/c/access-{date}.log', // eg: Log path, a daily log with a file name, currently only one macro variable {date} is supported. For example, the date format: 2022-02-22
//            'host' => 'host1', // Customize the host name, the default is the server host name if it is not set, which corresponds to the host field of clickhouse
//            'pattern' => '/\[(?P<created_at>.*)\] (?P<logger>\w+).(?P<level>\w+): (?P<message>.*[^ ]+) (?P<context>[^ ]+) (?P<extra>[^ ]+)/', // Optional configuration, if regular processing is not required, set to false
//            'callback' => function($data) { // Optional configuration, this line of data is processed according to a custom callback method, and the content of the method can implement any logic for cleaning this stream by itself.
//                $data['message'] = 'xxoo'; // For example, customize the processing of this data
//                return $data; // Need to return an array, key is the field name of the table in clickhouse, and value is the stored value
//            }
//            'clickhouse' => [...] // You can also configure the clickhouse connection information for individual projects, and the configuration content is the same as the clickhouse array of env.
        ],
    ],
];
```

### supervisord
It is recommended to use supervisor to manage your manager process.
```ini
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
