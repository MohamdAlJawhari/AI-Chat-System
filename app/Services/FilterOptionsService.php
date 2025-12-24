<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FilterOptionsService
{
    private const CACHE_KEY = 'filter_options:v1';

    /**
     * Return distinct category/country/city values from the news table,
     * cached to avoid hitting the database on every request.
     *
     * @return array{categories:array<int,string>,countries:array<int,string>,cities:array<int,string>}
     */
    public function get(): array
    {
        $ttlSeconds = (int) config('filter-options.cache_ttl_seconds', 43200); // 12h by default
        $ttl = now()->addSeconds(max(300, $ttlSeconds));

        return Cache::remember(self::CACHE_KEY, $ttl, function () {
            return [
                'categories' => $this->distinctValues('category'),
                'countries'  => $this->distinctValues('country'),
                'cities'     => $this->distinctValues('city'),
            ];
        });
    }

    /**
     * Manually clear and rebuild the cache (e.g., after new data ingest).
     */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->get();
    }

    /**
     * Pull distinct, trimmed values for a column on the news table.
     */
    private function distinctValues(string $column): array
    {
        $allowed = ['category', 'country', 'city'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        $values = DB::table('news')
            ->selectRaw('TRIM( ' . $column . ') as value')
            ->distinct()
            ->orderBy('value')
            ->whereNotNull($column)
            ->whereRaw('TRIM(' . $column . ') <> \'\'')
            ->pluck('value')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        return $values;
    }
}
