<?php

namespace App\Services\ArchiveRouting;

class AllowedFormatter
{
    /**
     * @param  array<int,string>  $values
     */
    public function formatAllowed(array $values, int $max): string
    {
        $values = array_values(array_filter(array_map(fn($v) => trim((string) $v), $values), fn($v) => $v !== ''));
        if (empty($values)) {
            return '[]';
        }
        if ($max > 0 && count($values) > $max) {
            $values = array_slice($values, 0, $max);
        }
        $encoded = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '[]';
    }

    /**
     * @param  array{min?:string|null,max?:string|null}|null  $range
     */
    public function formatDateRange(?array $range): string
    {
        $min = trim((string) ($range['min'] ?? ''));
        $max = trim((string) ($range['max'] ?? ''));

        if ($min !== '' && $max !== '') {
            return $min . '..' . $max;
        }
        if ($min !== '') {
            return 'from ' . $min;
        }
        if ($max !== '') {
            return 'up to ' . $max;
        }

        return 'unknown';
    }
}
