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
        protected string $organization_id,
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

    public function getCustomers() : array
    {
        return $this->handleResponse(
            $this->client->request('customers')
        )?->items ?? [];
    }

    public function getWebsites(bool $skip_disabled, ?string $org_id = null) : array
    {
        // If a specific org_id is provided, construct the path to query that organization
        // Otherwise, use the default organization set in the constructor
        $endpoint = $org_id ? "../{$org_id}/websites" : 'websites';
        
        $items = $this->handleResponse(
            $this->client->request($endpoint)
        )?->items;

        if ($skip_disabled)
            foreach ($items ?? [] as $key => $item)
                if ($item->status === 'disabled')
                    unset($items[$key]);

        return $items;
    }

    public function getDomains(string $website_id, ?string $org_id = null) : array
    {
        $endpoint = $org_id 
            ? "../{$org_id}/websites/{$website_id}/domains"
            : "websites/{$website_id}/domains";
            
        return $this->handleResponse(
            $this->client->request($endpoint)
        )?->items ?? [];
    }

    public function getDnsZone(string $website_id, string $domain_id, ?string $org_id = null) : ?object
    {
        $endpoint = $org_id
            ? "../{$org_id}/websites/{$website_id}/domains/{$domain_id}/dns-zone"
            : "websites/{$website_id}/domains/{$domain_id}/dns-zone";
            
        try {
            return $this->handleResponse(
                $this->client->request($endpoint)
            );
        } catch (EnhanceApiException $e) {
            if ($e->http_code === 404) {
                return null;
            }

            throw $e;
        }
    }
}