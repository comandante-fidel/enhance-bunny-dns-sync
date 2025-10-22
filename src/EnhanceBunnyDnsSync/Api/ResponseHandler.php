<?php

namespace EnhanceBunnyDnsSync\Api;

trait ResponseHandler
{
    protected function handleResponse(object $response) : object
    {
        $decoded = json_decode($response->body);

        if ($response->status_code >= 200 && $response->status_code < 300) {
            return is_object($decoded) ? $decoded : (object) [];
        }

        $exception_class = self::EXCEPTION_CLASS;
        throw new $exception_class(
            is_object($decoded) ? $decoded : null,
            $response->body,
            $response->status_code
        );
    }
}
