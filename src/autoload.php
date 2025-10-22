<?php

spl_autoload_register(function (string $class_name) : void {
    $base_dir = __DIR__ . '/';
    $path = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});