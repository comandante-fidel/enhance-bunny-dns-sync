<?php

namespace EnhanceBunnyDnsSync\Sync;

use EnhanceBunnyDnsSync\Dns\Record;
use EnhanceBunnyDnsSync\Dns\Zone;

trait Enhance
{
    /** @return Record[] */
    protected function collectEnhanceRecordsTable(Zone $dns_zone) : array
    {
        $enhance_records_table = [];

        foreach ($dns_zone->records as $record) {
            if (!in_array($record->type, self::SUPPORTED_RECORD_TYPES)) {
                continue;
            }

            // NS records on the root of the domain are not supported in Bunny
            if ($record->type === 'NS' && $record->name === '@') {
                continue;
            }

            $normalized_value = in_array($record->type, ['CNAME', 'MX', 'NS'])
                ? rtrim($record->value, '.')
                : $record->value
            ;

            $hash = hash('sha3-512', json_encode([
                'type' => $record->type,
                'name' => $record->name === '@' ? '' : $record->name,
                'value' => $normalized_value,
                'ttl' => self::normalizeBunnyTtl($record->ttl),
                'weight' => $record->weight,
                'priority' => $record->priority
            ]));

            $enhance_records_table[$hash] = $record;
        }

        return $enhance_records_table;
    }
}