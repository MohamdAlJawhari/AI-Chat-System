<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FilterOptionsService
{
    private const CACHE_KEY = 'filter_options:v2';

    /**
     * Return distinct category/country/city values from the news table,
     * cached to avoid hitting the database on every request.
     *
     * @return array{categories:array<int,string>,countries:array<int,string>,cities:array<int,string>,date_range:array{min:?string,max:?string}}
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
                'date_range' => $this->dateRange(),
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

    /**
     * Return the earliest/latest available date_sent values (YYYY-MM-DD).
     *
     * @return array{min:?string,max:?string}
     */
    private function dateRange(): array
    {
        $row = DB::table('news')
            ->selectRaw('MIN(date_sent) as min_date, MAX(date_sent) as max_date')
            ->whereNotNull('date_sent')
            ->first();

        $min = $this->formatDate($row->min_date ?? null);
        $max = $this->formatDate($row->max_date ?? null);

        return [
            'min' => $min,
            'max' => $max,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
