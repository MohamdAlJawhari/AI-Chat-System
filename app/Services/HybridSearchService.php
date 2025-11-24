<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HybridSearchService
{
    public function __construct(
        private readonly OllamaEmbeddingService $embedding,
    ) {
    }

    /**
     * Run the hybrid search function and return ranked news documents.
     *
     * @param  string  $query
     * @param  array{limit?:int,per_doc?:int,alpha?:float,beta?:float,ef_search?:int}  $options
     * @return array<int, object>
     */
    public function searchDocuments(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = (int) ($options['limit'] ?? config('rag.doc_limit', 10));
        if ($limit < 0) {
            $limit = 0;
        }

        $perDoc = max(1, (int) ($options['per_doc'] ?? config('rag.per_doc', 3)));
        $alpha = (float) ($options['alpha'] ?? config('rag.alpha', 0.80));
        $beta = (float) ($options['beta'] ?? config('rag.beta', 0.20));
        $efSearch = (int) ($options['ef_search'] ?? config('rag.hnsw_ef_search', 160));

        $vectors = $this->embedding->embed($query);
        $vec = $vectors[0] ?? null;
        if (!$vec) {
            return [];
        }

        $vecStr = 'ARRAY[' . implode(',', array_map(fn($x) => sprintf('%.7f', $x), $vec)) . ']::vector(1024)';

        if ($efSearch > 0) {
            DB::statement("SET hnsw.ef_search = {$efSearch}");
        }

        $sql = "
            SELECT news_item_id, doc_score, title, introduction, body, best_snippet
            FROM hybrid_search_news_docs(?, {$vecStr}, ?, ?, ?, ?)
        ";

        return DB::select($sql, [
            $query,
            $limit,
            $perDoc,
            $alpha,
            $beta,
        ]);
    }
}
