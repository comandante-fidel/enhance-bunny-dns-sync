<?php

namespace EnhanceBunnyDnsSync\Dns;

use EnhanceBunnyDnsSync\Common;
use EnhanceBunnyDnsSync\LogLevel;

/** @property Record[] $records */
class Zone
{
    const string META_FILE_EXTENSION = '.json';

    protected string $meta_file_path;
    public int $version = 1;
    protected ?string $initial_hash = null;
    protected ?string $current_hash = null;
    protected bool $finalized = false;
    protected(set) bool $need_sync = false;
    public ?int $bunny_id = null;
    public ?int $last_synced_change = null;

    const array FIELDS_TO_STORE = [
        'version',
        'hash' => 'initial_hash',
        'bunny_id',
        'last_synced_change'
    ];

    public string $domain {
        get {
            return rtrim($this->origin, '.');
        }
    }

    public function __construct(
        protected(set) string $origin,
        protected(set) object $soa,
        protected(set) array $records,
        string $folder_path
    ) {
        $this->readMetaFile($folder_path, Common::sanitize($this->origin));
    }

    protected function readMetaFile(string $folder_path, string $sanitized_filename) : void
    {
        $this->meta_file_path = "{$folder_path}/{$sanitized_filename}" . self::META_FILE_EXTENSION;

        if (!file_exists($this->meta_file_path) || !($contents = @file_get_contents($this->meta_file_path))) {
            return;
        }

        if (!is_object($decoded = json_decode($contents))) {
            return;
        }

        foreach (self::FIELDS_TO_STORE as $field_in_meta => $field_in_class) {
            $field_in_meta = is_int($field_in_meta) ? $field_in_class : $field_in_meta;

            if (isset($decoded->$field_in_meta)) {
                $this->$field_in_class = $decoded->$field_in_meta;
            }
        }
    }

    public function finalize() : self
    {
        usort($this->records, function(Record $a, Record $b) {
            return strcmp(serialize($a), serialize($b));
        });

        $this->current_hash = hash('sha3-512', json_encode([
            'origin' => $this->origin,
            'soa' => $this->soa,
            'records' => $this->records,
        ]));

        if ($this->current_hash !== $this->initial_hash) {
            $word = $this->initial_hash ? 'changed' : 'discovered';

            Common::log(
                $this->current_hash ? "Zone {$this->domain} {$word} in Enhance" : "New zone {$this->domain} in Enhance",
                LogLevel::Important
            );

            $this->need_sync = true;
            $this->writeMeta();
        }

        $this->finalized = true;

        return $this;
    }

    public function writeMeta() : self
    {
        $prepared_fields = [];

        foreach (self::FIELDS_TO_STORE as $field_in_meta => $field_in_class) {
            $field_in_meta = is_int($field_in_meta) ? $field_in_class : $field_in_meta;
            $prepared_fields[$field_in_meta] = $this->$field_in_class;
        }

        file_put_contents($this->meta_file_path, json_encode(array_merge($prepared_fields, [
            'records_count' => count($this->records),
            'hash' => $this->current_hash
        ])));

        return $this;
    }

    public static function createFromEnhance(object $zone, string $zones_folder) : self
    {
        $dns_zone = new self(
            origin: $zone->origin,
            soa: $zone->soa,
            records: [],
            folder_path: $zones_folder
        );

        foreach ($zone->records ?? [] as $record) {
            $dns_zone->records[] = Record::createFromEnhance($record);
        }

        return $dns_zone;
    }
}