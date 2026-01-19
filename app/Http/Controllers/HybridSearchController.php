<?php

// app/Http/Controllers/HybridSearchController.php
namespace App\Http\Controllers;

use App\Services\ArchiveFilterRouter;
use App\Services\HybridSearchService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class HybridSearchController extends Controller
{
    private const DEFAULT_BATCH_SIZE = 50;
    private const MIN_BATCH_SIZE = 10;
    private const MAX_BATCH_SIZE = 200;

    public function index(Request $r, HybridSearchService $search, ArchiveFilterRouter $router)
    {
        $q = trim($r->get('q', ''));

        // ----- LIMIT / K_DOCS -----
        $rawLimit = $r->input('limit', 10);
        $limit = null;

        if (is_numeric($rawLimit)) {
            $limit = (int) $rawLimit;
        } elseif (is_string($rawLimit) && strtolower($rawLimit) === 'all') {
            $limit = 0;  // unbounded
        }

        if ($limit === null) {
            $limit = 10;
        }
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 0) {
            $limit = min($limit, 200);
        }

        // ----- PAGINATION -----
        $page = max((int) $r->input('page', 1), 1);

        $batchInput = $r->input('batch', self::DEFAULT_BATCH_SIZE);
        $batchSize = is_numeric($batchInput) ? (int) $batchInput : self::DEFAULT_BATCH_SIZE;
        $batchSize = max(self::MIN_BATCH_SIZE, min(self::MAX_BATCH_SIZE, $batchSize));

        $shouldPaginate = $limit === 0;
        $requestedDocs = $shouldPaginate ? $batchSize * $page : $limit;
        $kDocs = $shouldPaginate ? ($requestedDocs + 1) : $limit;

        // ----- SEARCH PARAMETERS (NEW!) -----
        $alpha = (float) $r->input('alpha', config('rag.alpha', 0.80)); // semantic weight
        $beta = (float) $r->input('beta', config('rag.beta', 0.20));    // doc-level blend
        $autoFilters = $r->boolean('auto_filters');
        $autoWeights = $r->boolean('auto_weights');
        $autoDecision = null;
        if (($autoFilters || $autoWeights) && $q !== '') {
            $autoDecision = $router->route($q);
        }

        if ($autoWeights && is_array($autoDecision)) {
            $autoWeightsInput = is_array($autoDecision['weights'] ?? null) ? $autoDecision['weights'] : [];
            $alpha = is_numeric($autoWeightsInput['alpha'] ?? null)
                ? (float) $autoWeightsInput['alpha']
                : (float) config('rag.alpha', 0.80);
            $beta = is_numeric($autoWeightsInput['beta'] ?? null)
                ? (float) $autoWeightsInput['beta']
                : (float) config('rag.beta', 0.20);
        }
        $alpha = min(max($alpha, 0.0), 1.0);
        $beta = min(max($beta, 0.0), 1.0);

        $perDoc = (int) $r->input('per_doc', 3);       // chunks per doc
        $efSearch = (int) $r->input('ef_search', 160); // HNSW parameter

        // ----- OPTIONAL FILTERS -----
        $filtersInput = [
            'category' => trim((string) $r->input('category', '')),
            'country' => trim((string) $r->input('country', '')),
            'city' => trim((string) $r->input('city', '')),
            'date_from' => trim((string) $r->input('date_from', '')),
            'date_to' => trim((string) $r->input('date_to', '')),
            'is_breaking_news' => $r->input('is_breaking_news', ''),
        ];

        if ($autoFilters && is_array($autoDecision)) {
            $autoFiltersInput = is_array($autoDecision['filters'] ?? null) ? $autoDecision['filters'] : [];
            foreach ($autoFiltersInput as $key => $value) {
                if (!array_key_exists($key, $filtersInput) || $filtersInput[$key] === '' || $filtersInput[$key] === null) {
                    $filtersInput[$key] = $value;
                }
            }
        }

        $category = trim((string) ($filtersInput['category'] ?? ''));
        $country = trim((string) ($filtersInput['country'] ?? ''));
        $city = trim((string) ($filtersInput['city'] ?? ''));
        $dateFromRaw = trim((string) ($filtersInput['date_from'] ?? ''));
        $dateToRaw = trim((string) ($filtersInput['date_to'] ?? ''));

        $rawBreaking = $filtersInput['is_breaking_news'] ?? '';
        $normalizedBreaking = is_string($rawBreaking) ? strtolower(trim($rawBreaking)) : $rawBreaking;
        $isBreaking = null;
        if ($normalizedBreaking !== '' && $normalizedBreaking !== null) {
            $truthy = ['1', 1, true, 'true', 'yes', 'on'];
            $falsy = ['0', 0, false, 'false', 'no', 'off'];

            if (in_array($normalizedBreaking, $truthy, true)) {
                $isBreaking = true;
            } elseif (in_array($normalizedBreaking, $falsy, true)) {
                $isBreaking = false;
            }
        }

        $filters = [
            'category' => $category !== '' ? $category : null,
            'country' => $country !== '' ? $country : null,
            'city' => $city !== '' ? $city : null,
            'date_from' => $dateFromRaw !== '' ? $dateFromRaw : null,
            'date_to' => $dateToRaw !== '' ? $dateToRaw : null,
            'is_breaking_news' => $isBreaking,
        ];

        // Normalize dates to ISO strings for the SQL function and reorder if needed
        $dateFrom = null;
        $dateTo = null;

        if ($dateFromRaw !== '') {
            try {
                $dateFrom = CarbonImmutable::parse($dateFromRaw)->startOfDay();
                $dateFromRaw = $dateFrom->toDateString();
            } catch (\Throwable $e) {
                $dateFromRaw = '';
                $dateFrom = null;
            }
        }

        if ($dateToRaw !== '') {
            try {
                $dateTo = CarbonImmutable::parse($dateToRaw)->endOfDay();
                $dateToRaw = $dateTo->toDateString();
            } catch (\Throwable $e) {
                $dateToRaw = '';
                $dateTo = null;
            }
        }

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            [$dateFromRaw, $dateToRaw] = [$dateFrom->toDateString(), $dateTo->toDateString()];
        }

        $filters['date_from'] = $dateFromRaw !== '' ? $dateFromRaw : null;
        $filters['date_to'] = $dateToRaw !== '' ? $dateToRaw : null;

        $searchFilters = [
            'category' => $filters['category'],
            'country' => $filters['country'],
            'city' => $filters['city'],
            'date_from' => $dateFrom?->toIso8601String(),
            'date_to' => $dateTo?->toIso8601String(),
            'is_breaking_news' => $isBreaking,
        ];

        $results = [];
        $hasMore = false;

        if ($q !== '') {
            $results = $search->searchDocuments($q, [
                'limit'     => $kDocs,
                'alpha'     => $alpha,
                'beta'      => $beta,
                'per_doc'   => $perDoc,
                'ef_search' => $efSearch,
                'filters'   => $searchFilters,
            ]);

            // ----- PAGINATION LOGIC (UNCHANGED) -----
            if ($shouldPaginate) {
                if (count($results) > $requestedDocs) {
                    $hasMore = true;
                    $results = array_slice($results, 0, $requestedDocs);
                }

                $offset = ($page - 1) * $batchSize;
                $results = array_slice($results, $offset, $batchSize);
            }
        }

        $pagination = null;
        if ($shouldPaginate) {
            $pagination = [
                'page'     => $page,
                'batch'    => $batchSize,
                'has_more' => $hasMore,
            ];
        }

        return view('search', [
            'q'          => $q,
            'results'    => $results,
            'limit'      => $limit,
            'pagination' => $pagination,
            'alpha'      => $alpha,
            'beta'       => $beta,
            'per_doc'    => $perDoc,
            'ef_search'  => $efSearch,
            'filters'    => $filters,
        ]);
    }
}
