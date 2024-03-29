<?php
/**
 * User: hi@hisune.com
 * Date: 2022/02/17/0017
 * Time: 11:43
 */
declare(strict_types=1);

namespace Hisune\Log2Ck;

use OneCk\CkException;
use OneCk\Client;
use Exception;
use SplFileObject;
use Throwable;

if (php_sapi_name() != 'cli') exit();

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'autoload.php';

class Worker
{
    use ToolsTraits;

    const DEFAULT_PATTERN = '/\[(?P<created_at>.*)\] (?P<logger>\w+).(?P<level>\w+): (?P<message>.*[^ ]+) (?P<context>[^ ]+) (?P<extra>[^ ]+)/';

    protected $name;

    protected $tail;

    protected $path;

    protected $fileObject;

    protected $db;

    protected $cacheFilePath;

    protected $onProcess = true;

    protected $sentLastAt;

    protected $sentCount = 0;

    protected $sentData = [];

    protected $line = '';

    /**
     * @param string $name 日志名称，tails中的数组key
     * @param string $path 日志路径
     * @param string $configPath 配置文件路径
     * @param int|null $index 可选，开始tail的index，从0开始
     * @throws Exception
     */
    public function __construct(string $name, string $path, string $configPath, int $index = null)
    {
        $this->initConfig($configPath);
        $this->name = $name;
        $this->path = $path;
        $this->tail = $this->config['tails'][$name];
        $this->cacheFilePath = ($this->config['env']['worker']['cache_path'] ?? '/dev/shm/') . 'log2ck_worker_'. $name .'.cache';
        if(!is_null($index)){
            $this->setCurrentLines($index);
        }
    }

    /**
     * @throws CkException
     */
    protected function initClickhouse()
    {
        $this->db = new Client(
            $this->getClickhouseParam('dsn'),
            $this->getClickhouseParam('username'),
            $this->getClickhouseParam('password'),
            $this->getClickhouseParam('database'),
            $this->getClickhouseParam('options')
        );
    }

    public function run()
    {
        $this->handelSignal();

        $this->logger('worker', sprintf('start worker name: %s, path %s, config %s', $this->name, $this->path, $this->configPath));
        $this->initClickhouse();
        $this->logger('worker', sprintf('receive stdin: %s', $this->name));

        if(!file_exists($this->path)){
            $this->logger('worker', sprintf('file not exists: %s', $this->path));
            exit();
        }
        $this->fileObject = new SplFileObject($this->path);
        $this->fileObject->seek($this->getCurrentLines());
        $this->sentLastAt = time();
        $this->line = '';
        while($this->onProcess){
            $this->batchWrite();
            $line = $this->fileObject->current();
            if($this->fileObject->eof()){ // 没有新行
                sleep(1);
                if($line){ // 处理未写入完整的行
                    $this->line .= $line;
                }
                $this->fileObject->fseek(0, SEEK_CUR);
                continue;
            }
            $this->fileObject->next(); // 指针+1
            $this->line .= $line;
            $this->line = rtrim($this->line, PHP_EOL);
            if($this->line){
                $this->progressLine();
            }
            $this->line = '';
        }
    }

    protected function batchWrite()
    {
        $now = time();
        if($this->sentData && ($this->sentCount >= $this->getClickhouseParam('max_sent_count') || $now - $this->sentLastAt >  $this->getClickhouseParam('max_sent_wait'))){
            $this->db->insert($this->getClickhouseParam('table'), $this->sentData);
            $this->setCurrentLines($this->fileObject->key()); // 记录最后位置
            $this->sentCount = 0;
            $this->sentData = [];
            $this->sentLastAt = $now;
        }
    }

    protected function progressLine()
    {
        // 自定义正则或使用默认正则
        $pattern = $this->getPattern();
        if($pattern){
            preg_match($pattern, $this->line, $data);
            $data = array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
        }else{
            $data = $this->line;
        }
        if($data){
            if(isset($this->tail['callback'])){
                $data = $this->tail['callback']($data);
            }
            if(is_array($data)){
                $data['repo'] = $this->tail['repo'];
                $data['name'] = $this->name;
                $data['host'] = $this->tail['host'] ?? gethostname();
                $this->sentCount++;
                $this->sentData[] = $data;
            }else{
                $this->logger('worker', sprintf('not valid data: %s, stdin: %s', $this->tail['path'], json_encode($data)));
            }
        }else{
            $this->logger('worker', sprintf('not valid line: %s, stdin: %s', $this->tail['path'], $this->line));
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
    public function getCurrentLines(): int
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

    public function stopProcess()
    {
        $this->logger('worker', sprintf('stop process %s gracefully', $this->name));
        $this->onProcess = false;
    }

    public function getSentData(): array
    {
        return $this->sentData;
    }

}

$index = isset($argv[4]) ? intval($argv[4]) :  null;
$worker = new Worker($argv[1], $argv[2], $argv[3], $index);
while (true){
    try{
        $worker->run();
        exit();
    }catch (Throwable $e){
        $worker->logger('worker', sprintf('%s worker_exception: %s', $argv[1], $e->getMessage()), [
            'file' => $e->getFile(),
            'current' => $worker->getCurrentLines(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'data' => $worker->getSentData(),
            'memory' => memory_get_usage(),
        ]);
        sleep(10);
        exit();
    }
}

