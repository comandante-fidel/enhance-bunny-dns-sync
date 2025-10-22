<?php

namespace EnhanceBunnyDnsSync\Sync;

enum BunnyRecordType : int
{
    case A         = 0;
    case AAAA      = 1;
    case CNAME     = 2;
    case TXT       = 3;
    case MX        = 4;
    case Redirect  = 5;
    case Flatten   = 6;
    case PullZone  = 7;
    case SRV       = 8;
    case CAA       = 9;
    case PTR       = 10;
    case Script    = 11;
    case NS        = 12;

    public static function resolveByName(string $name) : ?int
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case->value;
            }
        }

        return null;
    }
}
