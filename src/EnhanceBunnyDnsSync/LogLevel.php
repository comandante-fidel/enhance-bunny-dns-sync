<?php

namespace EnhanceBunnyDnsSync;

enum LogLevel: string
{
    case Info = 'info';
    case Important = 'important';
    case Error = 'error';
    case Debug = 'debug';

    public function getEmoji() : string
    {
        return match($this) {
            self::Info => 'ℹ️',
            self::Important => '⚠️',
            self::Error => '❌ ',
            self::Debug => '🔍'
        };
    }
}
