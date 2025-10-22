<?php

namespace EnhanceBunnyDnsSync\Api;

use EnhanceBunnyDnsSync\HttpClient;

readonly class EnhanceApi
{
    use ResponseHandler;

    const string EXCEPTION_CLASS = EnhanceApiException::class;

    protected HttpClient $client;

    public function __construct(
        string $host,
        string $organization_id,
        string $access_token,
    ) {
        $this->client = new HttpClient(
            base_uri: "https://{$host}/api/orgs/{$organization_id}/",
            default_headers: [
                'Authorization' => "Bearer {$access_token}",
                'Accept' => 'application/json'
            ]
        );
    }

    public function getWebsites(bool $skip_disabled) : array
    {
        $items = $this->handleResponse(
            $this->client->request('websites')
        )?->items;

        if ($skip_disabled)
            foreach ($items ?? [] as $key => $item)
                if ($item->status === 'disabled')
                    unset($items[$key]);

        return $items;
    }

    public function getDomains(string $website_id) : array
    {
        return $this->handleResponse(
            $this->client->request("websites/{$website_id}/domains")
        )?->items ?? [];
    }

    public function getDnsZone(string $website_id, string $domain_id) : ?object
    {
        try {
            return $this->handleResponse(
                $this->client->request("websites/{$website_id}/domains/{$domain_id}/dns-zone")
            );
        } catch (EnhanceApiException $e) {
            if ($e->http_code === 404) {
                return null;
            }

            throw $e;
        }
    }
}