<?php

// app/Http/Controllers/HybridSearchController.php
namespace App\Http\Controllers;

use App\Services\OllamaEmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HybridSearchController extends Controller
{
    private const DEFAULT_BATCH_SIZE = 50;
    private const MIN_BATCH_SIZE = 10;
    private const MAX_BATCH_SIZE = 200;

    public function index(Request $r, OllamaEmbeddingService $emb)
    {
        $q = trim($r->get('q', ''));
        $rawLimit = $r->input('limit', 10);
        $limit = null;

        if (is_numeric($rawLimit)) {
            $limit = (int) $rawLimit;
        } elseif (is_string($rawLimit) && strtolower($rawLimit) === 'all') {
            $limit = 0;
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

        $page = max((int) $r->input('page', 1), 1);
        $batchInput = $r->input('batch', self::DEFAULT_BATCH_SIZE);
        $batchSize = is_numeric($batchInput) ? (int) $batchInput : self::DEFAULT_BATCH_SIZE;
        $batchSize = max(self::MIN_BATCH_SIZE, min(self::MAX_BATCH_SIZE, $batchSize));
        $shouldPaginate = $limit === 0;
        $requestedDocs = $shouldPaginate ? $batchSize * $page : $limit;
        $kDocs = $shouldPaginate ? ($requestedDocs + 1) : $limit;

        $results = [];
        $hasMore = false;

        if ($q !== '') {
            $vec = $emb->embed($q)[0];
            $vecStr = 'ARRAY[' . implode(',', array_map(fn($x) => sprintf('%.7f', $x), $vec)) . ']::vector(1024)';

            DB::statement("SET hnsw.ef_search = 160");

            $sql = "
                    SELECT news_item_id, doc_score, title, introduction, body, best_snippet
                    FROM hybrid_search_news_docs(?, {$vecStr}, ?, ?, ?, ?)
                ";
            $results = DB::select($sql, [
                $q,        // query_text
                $kDocs,    // k_docs (how many full articles to return for this request)
                3,         // per_doc (chunks considered per doc internally)
                0.80,      // alpha (semantic weight)
                0.20       // beta  (chunk-lex weight inside lexical blend)
            ]);

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
                'page' => $page,
                'batch' => $batchSize,
                'has_more' => $hasMore,
            ];
        }

        return view('search', [
            'q' => $q,
            'results' => $results,
            'limit' => $limit,
            'pagination' => $pagination,
        ]);
    }
}
