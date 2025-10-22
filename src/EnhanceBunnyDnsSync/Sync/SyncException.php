<?php

namespace EnhanceBunnyDnsSync\Sync;

class SyncException extends \Exception
{
    public function __construct(
        public readonly SyncExceptionType $type,
        public readonly array $additional_data = []
    ) {
        parent::__construct($type->value);
    }
}
