<?php

namespace App\Services;

use App\Services\ArchiveRouting\Filters\BreakingFilterRouter;
use App\Services\ArchiveRouting\Filters\CategoryFilterRouter;
use App\Services\ArchiveRouting\Filters\CityFilterRouter;
use App\Services\ArchiveRouting\Filters\CountryFilterRouter;
use App\Services\ArchiveRouting\Filters\DateFromFilterRouter;
use App\Services\ArchiveRouting\Filters\DateToFilterRouter;
use App\Services\ArchiveRouting\Filters\WeightsRouter;
use Carbon\CarbonImmutable;

class ArchiveFilterRouter
{
    public function __construct(
        private readonly FilterOptionsService $options,
        private readonly CountryFilterRouter $countryRouter,
        private readonly CategoryFilterRouter $categoryRouter,
        private readonly CityFilterRouter $cityRouter,
        private readonly DateFromFilterRouter $dateFromRouter,
        private readonly DateToFilterRouter $dateToRouter,
        private readonly BreakingFilterRouter $breakingRouter,
        private readonly WeightsRouter $weightsRouter,
    ) {
    }

    /**
     * @return array{filters:array<string,mixed>,weights:array{alpha:float,beta:float},reason:string,source:string}
     */
    public function route(string $query, array $overrides = []): array
    {
        $content = trim($query);
        $defaultAlpha = (float) config('rag.alpha', 0.80);
        $defaultBeta = (float) config('rag.beta', 0.20);

        if ($content === '') {
            return [
                'filters' => [],
                'weights' => ['alpha' => $defaultAlpha, 'beta' => $defaultBeta],
                'reason' => 'Auto router fallback: empty query',
                'source' => 'auto-fallback',
            ];
        }

        $allowed = $this->options->get();
        $model = $this->resolveModel($overrides);
        $llmOptions = $this->routerOptions();

        $rawFilters = [];
        $fallbacks = [];
        $llmUsed = false;

        $countryResult = $this->countryRouter->route($content, $allowed['countries'] ?? [], $model, $llmOptions);
        $llmUsed = $llmUsed || $countryResult['used'];
        $rawFilters['country'] = $countryResult['value'];
        if ($rawFilters['country'] === null) {
            $fallback = $this->matchAllowed($content, $allowed['countries'] ?? []);
            if ($fallback !== null) {
                $rawFilters['country'] = $fallback;
                $fallbacks[] = 'country';
            }
        }

        $categoryResult = $this->categoryRouter->route($content, $allowed['categories'] ?? [], $model, $llmOptions);
        $llmUsed = $llmUsed || $categoryResult['used'];
        $rawFilters['category'] = $categoryResult['value'];

        $cityResult = $this->cityRouter->route($content, $allowed['cities'] ?? [], $model, $llmOptions);
        $llmUsed = $llmUsed || $cityResult['used'];
        $rawFilters['city'] = $cityResult['value'];

        $dateRange = is_array($allowed['date_range'] ?? null) ? $allowed['date_range'] : null;
        $dateFromResult = $this->dateFromRouter->route($content, $dateRange, $model, $llmOptions);
        $llmUsed = $llmUsed || $dateFromResult['used'];
        $rawFilters['date_from'] = $dateFromResult['value'];

        $dateToResult = $this->dateToRouter->route($content, $dateRange, $model, $llmOptions);
        $llmUsed = $llmUsed || $dateToResult['used'];
        $rawFilters['date_to'] = $dateToResult['value'];

        $breakingResult = $this->breakingRouter->route($content, $model, $llmOptions);
        $llmUsed = $llmUsed || $breakingResult['used'];
        $rawFilters['is_breaking_news'] = $breakingResult['value'];

        $filters = $this->normalizeFilters($rawFilters, $allowed);

        $weightsResult = $this->weightsRouter->route($content, $defaultAlpha, $defaultBeta, $model, $llmOptions);
        $llmUsed = $llmUsed || $weightsResult['used'];
        $weights = $this->normalizeWeights($weightsResult['weights'], $defaultAlpha, $defaultBeta);

        $reason = $this->buildReason($llmUsed, $fallbacks);

        return [
            'filters' => $filters,
            'weights' => $weights,
            'reason' => $reason,
            'source' => $llmUsed ? 'auto-llm' : 'auto-fallback',
        ];
    }

    private function resolveModel(array $overrides): string
    {
        $modelOverride = $overrides['model'] ?? config('rag.auto_router.model');
        return trim((string) ($modelOverride ?: config('llm.model')));
    }

    /**
     * @return array<string,mixed>
     */
    private function routerOptions(): array
    {
        return [
            'temperature' => 0.0,
            'top_p' => 0.1,
            'repeat_penalty' => 1.0,
            'http_timeout' => (int) config('rag.auto_router.http_timeout', 12),
        ];
    }

    /**
     * @param  array<int,string>  $fallbacks
     */
    private function buildReason(bool $llmUsed, array $fallbacks): string
    {
        if (!$llmUsed) {
            return 'Auto router fallback: LLM unavailable';
        }

        if (empty($fallbacks)) {
            return 'LLM per-filter router applied';
        }

        return 'LLM per-filter router applied (fallback: ' . implode(', ', $fallbacks) . ')';
    }

    /**
     * @param  array<string,mixed>  $filters
     * @param  array{categories?:array<int,string>,countries?:array<int,string>,cities?:array<int,string>,date_range?:array{min?:string|null,max?:string|null}}  $allowed
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $filters, array $allowed): array
    {
        $categories = $allowed['categories'] ?? [];
        $countries = $allowed['countries'] ?? [];
        $cities = $allowed['cities'] ?? [];
        $dateRange = is_array($allowed['date_range'] ?? null) ? $allowed['date_range'] : [];

        $dateFrom = $this->normalizeDate($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDate($filters['date_to'] ?? null);
        if (!empty($dateRange)) {
            $dateFrom = $this->clampDateToRange($dateFrom, $dateRange);
            $dateTo = $this->clampDateToRange($dateTo, $dateRange);
        }

        $out = [
            'category' => $this->matchAllowed($filters['category'] ?? null, $categories),
            'country' => $this->matchAllowed($filters['country'] ?? null, $countries),
            'city' => $this->matchAllowed($filters['city'] ?? null, $cities),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'is_breaking_news' => $this->normalizeBreaking($filters['is_breaking_news'] ?? null),
        ];

        return array_filter($out, fn($v) => $v !== null);
    }

    /**
     * @return array{alpha:float,beta:float}
     */
    private function normalizeWeights(array $weights, float $defaultAlpha, float $defaultBeta): array
    {
        $alpha = $this->normalizeWeightValue($weights['alpha'] ?? null, $defaultAlpha);
        $beta = $this->normalizeWeightValue($weights['beta'] ?? null, $defaultBeta);

        return ['alpha' => $alpha, 'beta' => $beta];
    }

    private function normalizeWeightValue(mixed $value, float $fallback): float
    {
        if (!is_numeric($value)) {
            return $fallback;
        }
        $num = (float) $value;
        if ($num < 0.0) {
            return 0.0;
        }
        if ($num > 1.0) {
            return 1.0;
        }
        return $num;
    }

    private function normalizeBreaking(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            $truthy = ['1', 'true', 'yes', 'on'];
            $falsy = ['0', 'false', 'no', 'off'];

            if (in_array($normalized, $truthy, true)) {
                return true;
            }
            if (in_array($normalized, $falsy, true)) {
                return false;
            }
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int,string>  $allowed
     */
    private function matchAllowed(mixed $value, array $allowed): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        foreach ($allowed as $candidate) {
            if (strcasecmp((string) $candidate, $value) === 0) {
                return (string) $candidate;
            }
        }

        $normalized = $this->normalizeToken($value);
        if ($normalized === '') {
            return null;
        }

        $best = null;
        $bestLen = 0;
        foreach ($allowed as $candidate) {
            $candidate = (string) $candidate;
            $candidateNorm = $this->normalizeToken($candidate);
            if ($candidateNorm === '') {
                continue;
            }
            if ($candidateNorm === $normalized) {
                return $candidate;
            }
            if (strlen($normalized) >= 4 && (str_contains($candidateNorm, $normalized) || str_contains($normalized, $candidateNorm))) {
                $len = strlen($candidateNorm);
                if ($len > $bestLen) {
                    $best = $candidate;
                    $bestLen = $len;
                }
            }
        }

        return $best;
    }

    private function normalizeToken(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
        return $value;
    }

    /**
     * @param  array{min?:string|null,max?:string|null}  $range
     */
    private function clampDateToRange(?string $date, array $range): ?string
    {
        if ($date === null) {
            return null;
        }

        $min = $this->normalizeDate($range['min'] ?? null);
        $max = $this->normalizeDate($range['max'] ?? null);

        if ($min !== null && strcmp($date, $min) < 0) {
            return null;
        }
        if ($max !== null && strcmp($date, $max) > 0) {
            return null;
        }

        return $date;
    }
}
