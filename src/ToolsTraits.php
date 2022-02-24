<?php
namespace Hisune\Log2Ck;

trait ToolsTraits
{
    protected $config;

    protected $configPath;

    protected function initConfig($configPath)
    {
        ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'manager_error.log');

        if(!file_exists($configPath)) throw new \Exception('config file not found');
        $this->configPath = $configPath;
        $this->config = require_once $configPath;

        if(!isset($this->config['env']['clickhouse']['server']) || !is_array($this->config['env']['clickhouse']['server'])) throw new \Exception('not a valid clickhouse srever config');
        if(!isset($this->config['tails']) || !is_array($this->config['tails'])) throw new \Exception('not a valid tails config');
    }

    protected function logger(string $name, string $message, $context = [])
    {
        $line = date('Y-m-d H:i:s') . "\t" . $message . "\t" . (!is_scalar($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : $context) . PHP_EOL;
        echo $line;
        if(!isset($this->config['env']['logger']['enable']) || $this->config['env']['logger']['enable']){
            $logDir = $this->config['env']['logger']['path'] ?? __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
            if(!file_exists($logDir)) mkdir($logDir);
            error_log($line, 3, $logDir . $name . '-' . date('Y-m-d') . '.log');
        }

    }

}