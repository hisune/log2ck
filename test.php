<?php
use Hisune\Log2Ck\Manager;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

(new Manager(__DIR__ . DIRECTORY_SEPARATOR . 'test.config.php'))->run();