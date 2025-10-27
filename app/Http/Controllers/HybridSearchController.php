<?php

// app/Http/Controllers/HybridSearchController.php
namespace App\Http\Controllers;

use App\Services\OllamaEmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HybridSearchController extends Controller
{
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

        $results = [];

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
                $limit,    // k_docs (how many full articles to return; 0 means all)
                3,         // per_doc (chunks considered per doc internally)
                0.80,      // alpha (semantic weight)
                0.20       // beta  (chunk-lex weight inside lexical blend)
            ]);

        }

        return view('search', compact('q', 'results', 'limit'));
    }
}
