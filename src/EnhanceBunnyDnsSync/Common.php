<?php

namespace EnhanceBunnyDnsSync;

use EnhanceBunnyDnsSync\Sync\SyncException;

class Common
{
    public static string $log_path;
    public static bool $debug_log = false;

    const int LOG_MAX_LINES = 1000;
    const int LOG_MAX_SIZE = 50000;
    const string LOG_DATE_FORMAT = 'd.m H:i:s';

    public static function sanitize(string $input) : string
    {
        $input = preg_replace(
            ['/[^\w\-_.]/u', '/_+/'],
            ['_', '_'],
            trim($input)
        );

        $input = rtrim($input, '.');

        return $input;
    }

    public static function log(
        string $input,
        LogLevel $log_level = LogLevel::Info,
        bool $add_blank_line = false
    ) : void
    {
        if ($log_level === LogLevel::Debug && !self::$debug_log) {
            return;
        }

        $formatted_date = date(self::LOG_DATE_FORMAT);
        $string = $log_level->getEmoji() . " {$formatted_date} {$input}\r\n";

        if ($add_blank_line) {
            $string = "\r\n" . $string;
        }

        if (php_sapi_name() === 'cli') {
            echo $string;
        }

        file_put_contents(self::$log_path, $string, FILE_APPEND | LOCK_EX);
    }

    public static function trimLog() : void
    {
        if (is_file(self::$log_path) && filesize(self::$log_path) > self::LOG_MAX_SIZE) {
            $lines = file(self::$log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -self::LOG_MAX_LINES);
            file_put_contents(self::$log_path, implode("\r\n", $lines) . "\r\n", LOCK_EX);
        }
    }

    public static function clearLog() : void
    {
        file_put_contents(self::$log_path, '');
    }

    public static function getLog() : string
    {
        return file_get_contents(self::$log_path);
    }

    public static function convertSoaEmail(string $soa_email) : string
    {
        $soa_email = rtrim($soa_email, '.');
        $at_pos = strpos($soa_email, '.');

        if ($at_pos === false) {
            return $soa_email;
        }

        $local_part = substr($soa_email, 0, $at_pos);
        $domain_part = substr($soa_email, $at_pos + 1);

        return "{$local_part}@{$domain_part}";
    }

    public static function processException(SyncException $e) : void
    {
        $error_text = $e->type->value;

        if (!empty($e->additional_data)) {
            $error_text .= '. Details: ' . json_encode($e->additional_data, JSON_PRETTY_PRINT);
        }

        Common::log($error_text, LogLevel::Error);
    }

    public static function exit(mixed $message = '') : void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain');
        }

        exit($message);
    }

    public static function timeAgo(int $timestamp) : string
    {
        if (!$timestamp) {
            return 'never';
        }

        $plural = fn($n, $unit) => "$n $unit" . ((int) $n === 1 ? '' : 's');
        $diff = time() - $timestamp;

        return match(true) {
            $diff < 60     => 'just now',
            $diff < 3600   => $plural(floor($diff / 60), 'min') . ' ago',
            $diff < 86400  => $plural(floor($diff / 3600), 'hour') . ' ago',
            $diff < 604800 => $plural(floor($diff / 86400), 'day') . ' ago',
            default        => date('d.m.Y', $timestamp),
        };
    }

    public static function sendNotFoundHttpStatus() : void
    {
        header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 404 Not Found');
    }

    public static function sendJsonHeader() : void
    {
        header('Content-type: application/json');
    }

    public static function saveConfig(array $config) : bool
    {
        $result = (bool) file_put_contents(
            CONFIG_PATH,
            '<' . '?php return ' . var_export($config, true) . ';'
        );

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(CONFIG_PATH, true);
        }

        return $result;
    }
}