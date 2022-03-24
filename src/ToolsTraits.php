<?php
namespace Hisune\Log2Ck;

use Exception;

trait ToolsTraits
{
    protected $config;

    protected $configPath;

    protected $loggerDir;

    /**
     * @throws Exception
     */
    protected function initConfig($configPath)
    {
        ini_set('error_log', $this->getLoggerDir() . 'error.log');

        if(!file_exists($configPath)) throw new Exception('config file not found');
        $this->configPath = $configPath;
        $this->config = require_once $configPath;

        if(!isset($this->config['env']['clickhouse']['dsn'])) throw new Exception('not a valid clickhouse srever config');
        if(!isset($this->config['tails']) || !is_array($this->config['tails'])) throw new Exception('not a valid tails config');
    }

    protected function logger(string $name, string $message, $context = [])
    {
        $line = date('Y-m-d H:i:s') . "\t" . $message . "\t" . (!is_scalar($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : $context) . PHP_EOL;
        echo $line;
        if(!isset($this->config['env']['logger']['enable']) || $this->config['env']['logger']['enable']){
            error_log($line, 3, $this->getLoggerDir() . $name . '-' . date('Y-m-d') . '.log');
        }

    }

    protected function getLoggerDir()
    {
        if(is_null($this->loggerDir)) {
            $this->loggerDir = $this->config['env']['logger']['path'] ?? __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
            if(!file_exists($this->loggerDir)) mkdir($this->loggerDir);
        }
        return $this->loggerDir;
    }

    public function handelSignal()
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, [$this, 'stopProcess']);
        pcntl_signal(SIGHUP, [$this, 'stopProcess']);
        pcntl_signal(SIGINT, [$this, 'stopProcess']);
    }

}