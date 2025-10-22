<?php

use EnhanceBunnyDnsSync\Common;
use EnhanceBunnyDnsSync\Sync\Engine;
use EnhanceBunnyDnsSync\Sync\SyncException;

if (version_compare(PHP_VERSION, '8.4', '<')) {
    exit('⚠️ This application requires PHP ≥ 8.4 to run. Current PHP version: ' . PHP_VERSION);
}

const APP_PATH = __DIR__;

require_once APP_PATH . '/src/autoload.php';

const CONFIG_PATH = APP_PATH . '/config.php';

if (!is_readable(CONFIG_PATH)) {
    Common::exit('⚠️ Unable to load config.php');
}

$config = require CONFIG_PATH;

Common::$log_path = $config['system']['log_path'];

if ($config['system']['debug']) {
    Common::$debug_log = true;
    error_reporting(E_ALL ^ E_DEPRECATED);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

Common::trimLog();

try {
    $sync_engine = new Engine(...array_merge($config['sync'], $config['system']));
} catch (SyncException $e) {
    Common::processException($e);
}
