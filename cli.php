<?php

use EnhanceBunnyDnsSync\Common;
use EnhanceBunnyDnsSync\LogLevel;
use EnhanceBunnyDnsSync\Sync\Engine;
use EnhanceBunnyDnsSync\Sync\SyncException;

require_once __DIR__ . '/app.php';

$lock_file = fopen(APP_PATH . '/data/cli.lock', 'c');
if (!flock($lock_file, LOCK_EX | LOCK_NB)) {
    Common::exit('⚠️ Script is already running');
}

/** @var Engine $sync_engine */
try {
    Common::log('cli.php called from ' . php_sapi_name(), LogLevel::Debug);
    $sync_engine->sync();
} catch (SyncException $e) {
    Common::processException($e);
}

flock($lock_file, LOCK_UN);
fclose($lock_file);