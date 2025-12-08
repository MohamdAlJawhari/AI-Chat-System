<?php

// app/Http/Controllers/HybridSearchController.php
namespace App\Http\Controllers;

use App\Services\HybridSearchService;
use Illuminate\Http\Request;

class HybridSearchController extends Controller
{
    private const DEFAULT_BATCH_SIZE = 50;
    private const MIN_BATCH_SIZE = 10;
    private const MAX_BATCH_SIZE = 200;

    public function index(Request $r, HybridSearchService $search)
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
        $alpha = (float) $r->input('alpha', 0.80);     // semantic weight
        $perDoc = (int) $r->input('per_doc', 3);       // chunks per doc
        $efSearch = (int) $r->input('ef_search', 160); // HNSW parameter

        $results = [];
        $hasMore = false;

        if ($q !== '') {
            $results = $search->searchDocuments($q, [
                'limit'     => $kDocs,
                'alpha'     => $alpha,
                'per_doc'   => $perDoc,
                'ef_search' => $efSearch,
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
            'per_doc'    => $perDoc,
            'ef_search'  => $efSearch,
        ]);
    }
}
