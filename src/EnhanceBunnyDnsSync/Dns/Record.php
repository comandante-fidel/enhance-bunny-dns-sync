<?php

namespace EnhanceBunnyDnsSync\Dns;

class Record
{
    protected(set) string $type;
    protected(set) string $value = '' {
        set(string $input) {
            if ($this->type === 'MX' && str_contains($input, ' ')) {
                [$priority, $input] = explode(' ', $input, 2);
                $this->priority = $priority;
            }

            $this->value = $input;
        }
        get => $this->value;
    }
    protected(set) int $weight = 0;
    protected(set) int $priority = 0;
    protected(set) string $name;
    protected(set) int $ttl;
    protected(set) string $enhance_id;

    public static function createFromEnhance(object $record) : self
    {
        $dns_record = new self;

        $dns_record->type = $record->kind;
        $dns_record->name = $record->name;
        $dns_record->value = $record->value;
        $dns_record->ttl = $record->ttl;
        $dns_record->enhance_id = $record->id;

        return $dns_record;
    }
}