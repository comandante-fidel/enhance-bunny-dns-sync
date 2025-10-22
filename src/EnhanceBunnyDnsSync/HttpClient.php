<?php

namespace EnhanceBunnyDnsSync;

class HttpClient
{
    protected string $base_uri;

    const int HTTP_CODE_BAD_REQUEST = 400;
    const int HTTP_CODE_SERVER_ERROR = 500;
    const string HTTP_STATUS_SERVER_ERROR = 'HTTP/1.1 500 Internal Server Error';

    public function __construct(
        string $base_uri = '',
        protected readonly array $default_headers = []
    ) {
        $this->base_uri = rtrim($base_uri, '/');
    }

    public function request(
        string $uri,
        string $method = 'GET',
        array $headers = [],
        string $body = '',
        array $json = [],
        array $query = []
    ) : object
    {
        $url = $this->base_uri ? $this->base_uri . '/' . ltrim($uri, '/') : $uri;

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $merged_headers = array_merge($this->default_headers, $headers);

        if ($json !== []) {
            try {
                $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return (object) [
                    'status_code' => self::HTTP_CODE_BAD_REQUEST,
                    'body'        => 'Invalid JSON payload',
                ];
            }

            $merged_headers['Content-Type'] ??= 'application/json';
        }

        $context = stream_context_create([
            'http' => [
                'method'        => strtoupper($method),
                'header'        => $this->formatHeaders($merged_headers),
                'content'       => $body,
                'ignore_errors' => true
            ]
        ]);

        if (!($stream = @fopen($url, 'r', false, $context))) {
            return (object) [
                'status_code' => self::HTTP_CODE_SERVER_ERROR,
                'body'        => '',
            ];
        }

        $meta = stream_get_meta_data($stream);
        $response_body = stream_get_contents($stream);
        fclose($stream);

        $status_line = $meta['wrapper_data'][0] ?? self::HTTP_STATUS_SERVER_ERROR;
        $parts = explode(' ', $status_line, 3);
        $status_code = isset($parts[1]) ? (int) $parts[1] : self::HTTP_CODE_SERVER_ERROR;

        return (object) [
            'status_code' => $status_code,
            'body' => $response_body ?: '',
        ];
    }

    protected static function formatHeaders(array $headers) : string
    {
        return implode("\r\n", array_map(
            fn($key, $value) => "$key: $value",
            array_keys($headers),
            array_values($headers)
        ));
    }
}