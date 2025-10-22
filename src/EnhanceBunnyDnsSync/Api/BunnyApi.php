<?php

namespace EnhanceBunnyDnsSync\Api;

use EnhanceBunnyDnsSync\HttpClient;

readonly class BunnyApi
{
    use ResponseHandler;

    const string EXCEPTION_CLASS = BunnyApiException::class;

    protected HttpClient $client;

    public function __construct(string $api_key)
    {
        $this->client = new HttpClient(
            base_uri: 'https://api.bunny.net/',
            default_headers: [
                'Accept' => 'application/json',
                'AccessKey' => $api_key,
            ]
        );
    }

    public function addDnsRecord(int $zone_id, array $body) : object
    {
        return $this->handleResponse(
            $this->client->request(
                "dnszone/{$zone_id}/records",
                'PUT',
                json: $body
            )
        );
    }

    public function updateDnsRecord(int $zone_id, int $id, array $body) : object
    {
        return $this->handleResponse(
            $this->client->request(
                "dnszone/{$zone_id}/records/{$id}",
                'POST',
                json: $body
            )
        );
    }

    public function deleteDnsRecord(int $zone_id, int $id) : object
    {
        return $this->handleResponse(
            $this->client->request(
                "dnszone/{$zone_id}/records/{$id}",
                'DELETE'
            )
        );
    }

    public function getDnsZone(int $id) : object
    {
        return $this->handleResponse(
            $this->client->request("dnszone/{$id}")
        );
    }

    public function addDnsZone(array $body) : object
    {
        return $this->handleResponse(
            $this->client->request(
                'dnszone',
                'POST',
                json: $body
            )
        );
    }

    public function updateDnsZone(int $id, array $body) : object
    {
        return $this->handleResponse(
            $this->client->request(
                "dnszone/{$id}",
                'POST',
                json: $body
            )
        );
    }

    public function listDnsZones(array $query = []) : object
    {
        return $this->handleResponse(
            $this->client->request(
                'dnszone',
                query: $query
            )
        );
    }
}