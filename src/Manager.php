<?php
/**
 * User: hi@hisune.com
 * Date: 2022/02/17/0017
 * Time: 11:43
 */
declare(strict_types=1);
namespace Hisune\Log2Ck;

use Exception;
use Throwable;

if (php_sapi_name() != 'cli') exit();

class Manager
{
    use ToolsTraits;

    protected $workers = [];

    /**
     * @throws Exception
     */
    public function __construct(string $configPath)
    {
        $this->initConfig($configPath);
    }

    public function run()
    {
        try {
            $this->handelSignal();

            while (true) {
                $this->processTail();
                sleep(10);
            }
        }catch (Throwable $e){
            $this->logger('mamager', sprintf('manager_exception: %s', $e->getMessage()), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            sleep(10);
            $this->run();
        }
    }

    public function killWorker(string $name)
    {
        $cmd = "kill " . $this->workers[$name]['pid'];
        exec($cmd);
        $this->logger('manager', sprintf('killed worker %s: %s', $name, $cmd));
        unset($this->workers[$name]);
    }

    protected function processTail()
    {
        $today = date('Y-m-d');

        foreach($this->config['tails'] as $name => $tail){
            $index = null; // 是否指定文件开始的index
            // 进程已经不在了
            if(isset($this->workers[$name]['pid']) && !posix_kill($this->workers[$name]['pid'], 0)){
                $this->logger('manager', sprintf('worker dead %s: %s', $name, $this->workers[$name]['pid']));
                unset($this->workers[$name]);
            }
            /**
             * 判断是否已经跨天了
             * time() - mktime()为延迟几秒，防止前一个log内容没写完导致上报不完整
             */
            if(isset($this->workers[$name]['daily_log'])){
                if($this->workers[$name]['daily_log'] && $today != $this->workers[$name]['today'] && time() - mktime(0,0,0) > 10){
                    // kill掉子进程，走后面的创建子进程逻辑
                    $this->killWorker($name);
                    $index = 0;
                }else{
                    continue;
                }
            }

            if(strpos($tail['path'], '{') > 0){
                // 后面可以在这里扩展宏参数
                $path = str_replace(['{date}'], [$today], $tail['path']);
                $dailyLog = true;
            }else{
                $path = $tail['path'];
                $dailyLog = false;
            }
            if(!file_exists($path)){
                $this->logger('manager', 'ERROR: file not exists, skip tail', $path);
                continue;
            }
            $this->logger('manager', sprintf('start process %s with %s', $name, $path));
            $argsArray = [
                __DIR__ . DIRECTORY_SEPARATOR . 'Worker.php',
                $name,
                $path,
                $this->configPath,
            ];
            if(!is_null($index)){
                $argsArray = array_merge($argsArray, [$index]);
            }
            $args = join(' ', array_map('escapeshellarg', $argsArray));
            $cmd = 'nohup ' . ($this->config['env']['bin']['php'] ?? '/usr/bin/php') . ' ' . $args . ' > /dev/null 2>&1 & echo $!';
            $output = null;
            exec($cmd, $output);
            $pid = (int)$output[0];
            $this->workers[$name] = [
                'pid' => $pid,
                'daily_log' => $dailyLog,
                'today' => $today,
                'cmd' => $cmd,
            ];
            $this->logger('manager', 'stared process ' . $name, $this->workers[$name]);
        }
    }

    public function stopProcess()
    {
        foreach($this->workers as $name => $worker){
            $this->killWorker($name);
        }
        exit();
    }

}
