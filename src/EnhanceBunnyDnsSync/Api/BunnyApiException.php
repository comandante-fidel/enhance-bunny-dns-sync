<?php

namespace EnhanceBunnyDnsSync\Api;

use EnhanceBunnyDnsSync\Sync\SyncException;
use EnhanceBunnyDnsSync\Sync\SyncExceptionType;

class BunnyApiException extends SyncException
{
    public function __construct(
        public readonly ?object $response = null,
        public readonly string $raw_response = '',
        public readonly ?int $http_code = null
    ) {
        parent::__construct(SyncExceptionType::BUNNY_API_EXCEPTION, [
            'response' => $response,
            'raw_response' => $raw_response,
            'http_code' => $http_code
        ]);
    }
}
