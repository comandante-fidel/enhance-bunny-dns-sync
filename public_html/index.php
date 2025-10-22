<?php

use EnhanceBunnyDnsSync\Common;
use EnhanceBunnyDnsSync\Sync\Engine;
use EnhanceBunnyDnsSync\WebController;

require_once __DIR__ . '/../app.php';

/** @var Engine $sync_engine */
/** @var array $config */

$web_config = $config['web'];
$valid_username = $web_config['username'];
$valid_password = $web_config['password'];

if (!$valid_password) {
    Common::exit('⚠️ No password configured');
}

if (
    ($valid_username && (($_SERVER['PHP_AUTH_USER'] ?? '') !== $valid_username))
    || ($_SERVER['PHP_AUTH_PW'] ?? '') !== $valid_password
) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    Common::exit('⚠️ Authorization required');
}

$web_controller = new WebController($sync_engine, $config);
$action = $_GET['action'] ?? 'mainPage';

if (!method_exists($web_controller, $action) || str_starts_with($action, '__')) {
    Common::sendNotFoundHttpStatus();
    Common::exit('⚠️ Unknown method requested');
}

$web_controller->$action();