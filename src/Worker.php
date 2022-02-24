<?php
/**
 * User: hi@hisune.com
 * Date: 2022/02/17/0017
 * Time: 11:43
 */
declare(strict_types=1);

namespace Hisune\Log2Ck;

use ClickHouseDB\Client;
use SplFileObject;

if (php_sapi_name() != 'cli') exit();

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'autoload.php';

class Worker
{
    use ToolsTraits;

    const DEFAULT_PATTERN = '/\[(?P<created_at>.*)\] (?P<logger>\w+).(?P<level>\w+): (?P<message>.*[^ ]+) (?P<context>[^ ]+) (?P<extra>[^ ]+)/';

    protected $name;

    protected $tail;

    protected $path;

    protected $db;

    protected $cacheFilePath;

    public function __construct(string $name, string $path, string $configPath)
    {
        $this->initConfig($configPath);
        $this->name = $name;
        $this->path = $path;
        $this->tail = $this->config['tails'][$name];
        $this->cacheFilePath = ($this->config['env']['worker']['cache_path'] ?? '/dev/shm/') . 'log2ck_worker_'. $name .'.cache';
    }

    protected function initClickhouse()
    {
        $this->db = new Client($this->getClickhouseParam('server'));
        $this->db->database($this->getClickhouseParam('database'));
    }

    public function run()
    {
        try{
            $this->logger('worker', sprintf('start worker name: %s, path %s, config %s', $this->name, $this->path, $this->configPath));
            $this->initClickhouse();
            $this->logger('worker', sprintf('receive stdin: %s', $this->name));

            $file = new SplFileObject($this->path);
            $file->seek($this->getCurrentLines());
            while(true){
                $line = $file->current();
                if($file->eof()){ // 没有新内容
                    sleep(1);
                    $file->fseek(0, SEEK_CUR);
                    continue;
                }
                $file->next(); // 指针+1
                $line = trim($line);
                if($line){
                    // 自定义正则或使用默认正则
                    $pattern = $this->getPattern();
                    if($pattern){
                        preg_match($pattern, $line, $data);
                        $data = array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
                    }else{
                        $data = $line;
                    }
                    if($data){
                        if(isset($this->tail['callback'])){
                            $data = $this->tail['callback']($data);
                        }
                        if(is_array($data)){
                            $data['repo'] = $this->tail['repo'];
                            $data['name'] = $this->name;
                            $data['host'] = gethostname();
                            $this->db->insert($this->getClickhouseParam('table'), [$data], array_keys($data));
                        }else{
                            $this->logger('worker', sprintf('not valid data: %s, stdin: %s', $this->tail['path'], json_encode($data)));
                        }
                    }else{
                        $this->logger('worker', sprintf('not valid line: %s, stdin: %s', $this->tail['path'], $line));
                    }
                }
                $this->setCurrentLines($file->key()); // 记录最后位置
            }
        }catch (\Throwable $e){
            $this->logger('worker', sprintf('%s worker_exception: %s', $this->name, $e->getMessage()), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            sleep(10);
            $this->run();
        }
    }

    /**
     * 保存最后读取的行数
     */
    protected function setCurrentLines($number)
    {
        file_put_contents($this->cacheFilePath, $number);
    }

    /**
     * 获取最后读取行数，如果没有写入文件，则设置为文件最后一行
     * @return int
     */
    protected function getCurrentLines()
    {
        if(file_exists($this->cacheFilePath)){
            return (int)file_get_contents($this->cacheFilePath);
        }
        return PHP_INT_MAX;
    }

    protected function getPattern()
    {
        return $this->tail['pattern'] ?? self::DEFAULT_PATTERN;
    }

    protected function getClickhouseParam(string $key)
    {
        return $this->tail['clickhouse'][$key] ?? $this->config['env']['clickhouse'][$key];
    }

}

(new Worker($argv[1], $argv[2], $argv[3]))->run();
