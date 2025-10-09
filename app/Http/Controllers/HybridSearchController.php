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
                $q,    // query_text
                10,    // k_docs (how many full articles to return)
                3,     // per_doc (chunks considered per doc internally)
                0.60,  // alpha (semantic weight)
                0.40   // beta  (chunk-lex weight inside lexical blend)
            ]);

        }

        return view('search', compact('q', 'results'));
    }
}
