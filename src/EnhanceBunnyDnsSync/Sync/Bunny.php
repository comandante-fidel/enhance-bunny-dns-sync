<?php

namespace EnhanceBunnyDnsSync\Sync;

use EnhanceBunnyDnsSync\Api\BunnyApiException;
use EnhanceBunnyDnsSync\Common;
use EnhanceBunnyDnsSync\Dns\Zone;
use EnhanceBunnyDnsSync\LogLevel;

trait Bunny
{
    const array ALLOWED_BUNNY_TTLS = [15, 30, 60, 120, 300, 900, 1800, 3600, 18000, 43200, 86400];

    protected static function normalizeBunnyTtl(int $input) : int
    {
        return array_reduce(
            self::ALLOWED_BUNNY_TTLS,
            fn($closest, $current) =>
                abs($input - $current) < abs($input - $closest) ? $current : $closest,
            self::ALLOWED_BUNNY_TTLS[0]
        );
    }

    protected function collectBunnyRecordsTable(int $bunny_id) : array
    {
        $contents = $this->bunny_api->getDnsZone($bunny_id);
        $bunny_records_table = [];

        foreach ($contents->Records as $record) {
            $comment = $record->Comment;
            $comment_object = json_decode($comment);

            $saved_props = (object) [];

            if (is_object($comment_object) && isset($comment_object->enhance_bunny_dns_sync)) {
                $saved_props = $comment_object->enhance_bunny_dns_sync;
            }

            $enhance_record_id = $saved_props->enhance_record_id ?? null;
            $type = BunnyRecordType::tryFrom($record->Type)?->name;

            if (!$enhance_record_id && !in_array($type, self::SUPPORTED_RECORD_TYPES)) {
               continue;
            }

            $hash = hash('sha3-512', json_encode([
                'type' => $type,
                'name' => $record->Name,
                'value' => $record->Value,
                'ttl' => $record->Ttl,
                'weight' => $record->Weight,
                'priority' => $record->Priority
            ]));

            $bunny_records_table[$hash] = [
                'bunny_id' => $record->Id,
                'type_in_bunny' => $type,
                'name_in_bunny' => $record->Name,
                'enhance_id' => $enhance_record_id
            ];
        }

        return $bunny_records_table;
    }

    protected function createBunnyZone(Zone $dns_zone) : int
    {
        Common::log("Creating new zone {$dns_zone->domain} in Bunny.net", LogLevel::Important);

        try {
            $id = $this->bunny_api->addDnsZone(body: [
                'Domain' => $dns_zone->domain
            ])->Id ?? null;

            if (!$id) {
                throw new SyncException(SyncExceptionType::BUNNY_FAILED_TO_CREATE_ZONE);
            }

            if (!empty($this->custom_nameservers)) {
                Common::log('Enabling custom nameservers...');

                $this->bunny_api->updateDnsZone(
                    id: $id,
                    body: [
                        'CustomNameserversEnabled' => true,
                        'Nameserver1' => $this->custom_nameservers[0],
                        'Nameserver2' => $this->custom_nameservers[1],
                        'SoaEmail' => Common::convertSoaEmail($dns_zone->soa->adminEmail)
                    ]
                );
            }

            return $id;
        } catch (BunnyApiException $e) {
            if (($e->response->ErrorKey ?? null) === 'dnszone.name_taken') {
                throw new SyncException(SyncExceptionType::BUNNY_ZONE_ALREADY_EXISTS);
            }

            throw $e;
        }
    }

    protected function findBunnyZoneId(Zone $dns_zone) : int
    {
        Common::log("Looking up Bunny.net zone ID for origin: {$dns_zone->origin}", LogLevel::Important);

        $domain = $dns_zone->domain;

        $contents = $this->bunny_api->listDnsZones(query: [
            'page' => 1,
            'search' => $domain
        ]);

        foreach ($contents->Items ?? [] as $item) {
            if ($item->Domain === $domain) {
                return $item->Id;
            }
        }

        throw new SyncException(SyncExceptionType::BUNNY_ZONE_NOT_FOUND);
    }
}