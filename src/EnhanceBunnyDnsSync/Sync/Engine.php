<?php

namespace EnhanceBunnyDnsSync\Sync;

use EnhanceBunnyDnsSync\Api\BunnyApi;
use EnhanceBunnyDnsSync\Api\BunnyApiException;
use EnhanceBunnyDnsSync\Api\EnhanceApi;
use EnhanceBunnyDnsSync\Common;
use EnhanceBunnyDnsSync\Dns\Record;
use EnhanceBunnyDnsSync\Dns\Zone;
use EnhanceBunnyDnsSync\LogLevel;

class Engine
{
    use Bunny;
    use Enhance;

    const array SUPPORTED_RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT'];

    protected BunnyApi $bunny_api;
    protected EnhanceApi $enhance_api;

    public function __construct(
        protected string $dns_zones_folder,
        string $enhance_host,
        string $enhance_organization_id,
        string $enhance_api_key,
        string $bunny_api_key,
        protected array $custom_nameservers,
        string $log_path,
        protected bool $debug,
        protected string $dns_zones_filter_mode,
        protected array $dns_zones_list,
        protected bool $skip_disabled_websites
    ) {
        if (!empty($this->custom_nameservers) && count($this->custom_nameservers) < 2) {
            throw new SyncException(SyncExceptionType::WRONG_NAMESERVERS_NUMBER);
        }

        $this->initializeBunnyApi($bunny_api_key);
        $this->initializeEnhanceApi(
            $enhance_host,
            $enhance_organization_id,
            $enhance_api_key
        );
    }

    protected function initializeBunnyApi($bunny_api_key) : void
    {
        $this->bunny_api = new BunnyApi($bunny_api_key);
    }

    protected function initializeEnhanceApi($enhance_host, $enhance_organization_id, $enhance_api_key) : void
    {
        $this->enhance_api = new EnhanceApi(
            $enhance_host,
            $enhance_organization_id,
            $enhance_api_key
        );
    }

    public function sync(
        ?string $specific_zone = null,
        bool $dry_run = false
    ) : void
    {
        Common::log(
            'Starting synchronization' . ($dry_run ? ' (dry run)' : ''),
            LogLevel::Debug,
            add_blank_line: true
        );

        if ($specific_zone !== null) {
            Common::log("DNS zone {$specific_zone} synchronization has been requested");
        }

        $websites = $this->enhance_api->getWebsites($this->skip_disabled_websites);

        foreach ($websites as $website) {
            Common::log("Website: {$website->domain->domain}", LogLevel::Debug);

            foreach ($this->enhance_api->getDomains($website->id) as $domain) {
                if (
                    ($specific_zone !== null && $specific_zone !== $domain->domain)
                    || ($this->dns_zones_filter_mode === 'whitelist' && !in_array($domain->domain, $this->dns_zones_list))
                    || ($this->dns_zones_filter_mode === 'blacklist' && in_array($domain->domain, $this->dns_zones_list))
                ) {
                    continue;
                }

                $this->syncDomain(
                    domain: $domain,
                    website_id: $website->id,
                    dry_run: $dry_run
                );
            }
        }

        Common::log('Done',  !is_null($specific_zone) ? LogLevel::Info : LogLevel::Debug);
    }

    protected function syncDomain(
        object $domain,
        string $website_id,
        bool $dry_run = false
    ) : void
    {
        $dns_zone_data = $this->enhance_api->getDnsZone($website_id, $domain->domainId);

        if (is_null($dns_zone_data)) {
            Common::log("Skip empty DNS zone {$domain->domain}", LogLevel::Debug);
            return;
        }

        Common::log("Domain: {$domain->domain}", LogLevel::Debug);

        $dns_zone = Zone::createFromEnhance($dns_zone_data, $this->dns_zones_folder);
        $dns_zone->finalize();

        if (!$dry_run && ($dns_zone->need_sync || is_null($dns_zone->bunny_id))) {
            try {
                $this->syncZone($dns_zone);
            } catch (BunnyApiException $e) {
                if ($e->http_code === 401 || str_contains($e->raw_response, 'Authorization has been denied')) {
                    throw new SyncException(SyncExceptionType::BUNNY_WRONG_CREDENTIALS);
                }

                throw $e;
            }
        }
    }

    protected function syncZone(Zone $dns_zone) : self
    {
        Common::log("Syncing zone: {$dns_zone->origin}...", LogLevel::Debug);

        $sync_contents = function(?int $bunny_id = null) use($dns_zone) {
            if (!is_null($bunny_id)) {
                $dns_zone->bunny_id = $bunny_id;
            }

            $this->syncRecords($dns_zone);
            $dns_zone->writeMeta();
        };

        // We have ID of zone in the Bunny.net
        if ($dns_zone->bunny_id) {
            try {
                $sync_contents();
            } catch (SyncException $e) {
                // We have Bunny.net zone ID, but it is wrong. Try to create zone
                if ($e->type === SyncExceptionType::BUNNY_ZONE_DOES_NOT_EXISTS) {
                    Common::log("Wrong Bunny.net ID for zone {$dns_zone->domain}. Trying to create new zone...", LogLevel::Error);

                    try {
                        $sync_contents(
                            $this->createBunnyZone($dns_zone)
                        );
                    } catch (SyncException $e) {
                        // Zone already exists, but we had wrong ID. Need to find its real ID
                        if ($e->type === SyncExceptionType::BUNNY_ZONE_ALREADY_EXISTS) {
                            Common::log("Zone {$dns_zone->domain} found in Bunny.net, but we stored the wrong ID.", LogLevel::Error);
                            $sync_contents($this->findBunnyZoneId($dns_zone));
                        } else throw $e;
                    }
                } else throw $e;
            }
        // No Bunny.net DNS ID
        } else {
            Common::log('We do not have Bunny.net DNS ID for this zone.', LogLevel::Important);

            try {
                $sync_contents($this->createBunnyZone($dns_zone));
            } catch (SyncException $e) {
                if ($e->type === SyncExceptionType::BUNNY_ZONE_ALREADY_EXISTS) {
                    Common::log("Zone {$dns_zone->domain} found in Bunny.net, but we haven't stored its ID.", LogLevel::Important);
                    $sync_contents($this->findBunnyZoneId($dns_zone));
                } else throw $e;
            }
        }

        return $this;
    }

    protected function syncRecords(Zone $dns_zone) : self
    {
        Common::log(
            "Syncing records for {$dns_zone->domain}",
            LogLevel::Important
        );

        try {
            $bunny_records_table = $this->collectBunnyRecordsTable($dns_zone->bunny_id);
            $enhance_records_table = $this->collectEnhanceRecordsTable($dns_zone);

            // Prepare list of the records we should add to Bunny

            /** @var Record[] $records_to_add_in_bunny */
            $records_to_add_in_bunny = [];

            foreach ($enhance_records_table as $hash => $enhance_record) {
                if (!isset($bunny_records_table[$hash])) {
                    $records_to_add_in_bunny[] = $enhance_record;
                }
            }

            // Prepare list of the records we should remove or update in Bunny.net

            $records_to_delete_from_bunny = $records_to_update_on_bunny = [];

            foreach ($bunny_records_table as $hash => [
                     'bunny_id' => $bunny_record_id,
                     'type_in_bunny' => $type_in_bunny,
                     'name_in_bunny' => $name_in_bunny,
                     'enhance_id' => $enhance_record_id
            ]) {
                // We could not find a record in the Enhance with the same content as in Bunny.net record
                if (!isset($enhance_records_table[$hash])) {
                    // In the field "Comment" in Bunny.net, this tool stores the original Enhance ID of the record.
                    // So... Perhaps the Enhance DNS record with the specified ID exists (and with the same type and name),
                    // but its content has changed?
                    if (
                        $enhance_record_id
                        && $enhance_record = array_find(
                            $enhance_records_table,
                            fn($enhance_record) => $enhance_record->enhance_id === $enhance_record_id
                                && $enhance_record->type === $type_in_bunny
                                && $enhance_record->name === $name_in_bunny
                        )
                    ) {
                        // If so, we can just update existing record instead of deleting + adding
                        $records_to_update_on_bunny[$bunny_record_id] = $enhance_record;
                        // ... and therefore removing it from list of records to add
                        foreach ($records_to_add_in_bunny as $key => $record)
                            if ($record->enhance_id === $enhance_record_id)
                                unset($records_to_add_in_bunny[$key]);
                    }
                    // Nope, record does not exist in Enhance – nor by content, nor by ID,
                    // and we're going to delete it from Bunny
                    else {
                        $records_to_delete_from_bunny[] = $bunny_record_id;
                    }
                }
            }

            Common::log(
                'Records to add / update / delete: '
                . count($records_to_add_in_bunny) . ' / '
                . count($records_to_update_on_bunny) . ' / '
                . count($records_to_delete_from_bunny)
            );

            $create_add_or_update_body = function(Record $enhance_record) : array {
                return [
                    'Type' => BunnyRecordType::resolveByName($enhance_record->type),
                    'Ttl' => self::normalizeBunnyTtl($enhance_record->ttl),
                    'Value' => $enhance_record->value,
                    'Name' => $enhance_record->name,
                    'Weight' => $enhance_record->weight,
                    'Priority' => $enhance_record->priority,
                    'Comment' => json_encode([
                        'enhance_bunny_dns_sync' => [
                            'enhance_record_id' => $enhance_record->enhance_id,
                        ]
                    ])
                ];
            };

            // Add records
            foreach ($records_to_add_in_bunny as $enhance_record) {
                $this->bunny_api->addDnsRecord(
                    zone_id: $dns_zone->bunny_id,
                    body: $create_add_or_update_body($enhance_record)
                );
            }

            // Update records
            foreach ($records_to_update_on_bunny as $bunny_record_id => $enhance_record) {
                $body = $create_add_or_update_body($enhance_record);
                $body['Id'] = $bunny_record_id;
                $this->bunny_api->updateDnsRecord(
                    zone_id: $dns_zone->bunny_id,
                    id: $bunny_record_id,
                    body: $body
                );
            }

            // Delete records
            foreach ($records_to_delete_from_bunny as $bunny_record_id) {
                $this->bunny_api->deleteDnsRecord(
                    zone_id: $dns_zone->bunny_id,
                    id: $bunny_record_id
                );
            }
        } catch (BunnyApiException $e) {
            if (($e->response->ErrorKey ?? null) === 'dnsZone.not_found') {
                throw new SyncException(SyncExceptionType::BUNNY_ZONE_DOES_NOT_EXISTS);
            }

            throw $e;
        }

        if ($records_to_add_in_bunny > 0 || $records_to_update_on_bunny > 0 || $records_to_delete_from_bunny > 0) {
            $dns_zone->last_synced_change = time();
            $dns_zone->version += 1;
        }

        return $this;
    }

    public function getDnsZonesInfo() : array
    {
        $dns_zones = [];

        foreach (glob($this->dns_zones_folder . '/*' . Zone::META_FILE_EXTENSION) as $file) {
            $domain = basename($file, Zone::META_FILE_EXTENSION);
            $raw_content = @file_get_contents($file);
            $decoded = json_decode($raw_content, true);

            if (!is_array($decoded)) {
                continue;
            }

            $dns_zones[$domain] = array_merge($decoded, [
                'last_synced_change_detailed' => date('d.m.Y H:i:s', $decoded['last_synced_change'] ?? 0),
                'last_synced_change_human' => Common::timeAgo($decoded['last_synced_change'] ?? 0)
            ]);
        }

        return $dns_zones;
    }
}