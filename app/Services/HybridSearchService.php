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
     * @param  array{limit?:int,per_doc?:int,alpha?:float,beta?:float,ef_search?:int,filters?:array<string,mixed>}  $options
     *                       // limit    => k_docs (how many docs to return)
     *                       // per_doc  => how many chunks per doc to consider
     *                       // alpha    => semantic weight
     *                       // beta     => doc-level blend between best and average chunk scores
     *                       // ef_search => HNSW search quality
     *                       // filters  => optional dataset filters (category, country, city, is_breaking_news)
     * @return array<int, object>
     */
    public function searchDocuments(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // k_docs (how many docs to return) – allow 0 => unbounded mode
        $kDocs = (int) ($options['limit'] ?? config('rag.doc_limit', 10));
        if ($kDocs < 0) {
            $kDocs = 0;
        }

        // how many chunks per doc we consider when scoring
        $perDoc = max(1, (int) ($options['per_doc'] ?? config('rag.per_doc', 3)));

        // semantic vs lexical weight
        $alpha = (float) ($options['alpha'] ?? config('rag.alpha', 0.80));
        $beta = (float) ($options['beta'] ?? config('rag.beta', 0.20));
        $alpha = max(0.0, min(1.0, $alpha));
        $beta = max(0.0, min(1.0, $beta));

        // HNSW search parameter (optional tuning)
        $efSearch = (int) ($options['ef_search'] ?? config('rag.hnsw_ef_search', 160));

        // Optional filters to shrink the candidate set before hybrid search
        $filters = is_array($options['filters'] ?? null) ? $options['filters'] : [];
        $category = $this->nullIfEmpty($filters['category'] ?? null);
        $country = $this->nullIfEmpty($filters['country'] ?? null);
        $city = $this->nullIfEmpty($filters['city'] ?? null);
        $isBreaking = $filters['is_breaking_news'] ?? null;
        if (!is_null($isBreaking)) {
            $isBreaking = (bool) $isBreaking;
        }

        // 1) Embed the query (nomic-embed-text → 768 dim)
        $vectors = $this->embedding->embed($query);   // returns list of vectors
        $vec = $vectors[0] ?? null;
        if (!$vec) {
            return [];
        }

        // 2) Build Postgres vector literal (768 dims)
        $vecStr = 'ARRAY[' . implode(',', array_map(fn($x) => sprintf('%.7f', $x), $vec)) . ']::vector(768)';

        // 3) Optional HNSW tuning
        if ($efSearch > 0) {
            DB::statement("SET hnsw.ef_search = {$efSearch}");
        }

        // 4) Call your SQL function hybrid_search_docs(...)
        $sql = "
            SELECT
                news_id,
                doc_score,
                title,
                introduction,
                body,
                best_snippet
            FROM hybrid_search_docs(?, {$vecStr}, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        return DB::select($sql, [
            $query,   // query_text
            $kDocs,   // k_docs
            $perDoc,  // per_doc
            $alpha,   // alpha
            $beta,    // beta
            $category, // category filter
            $country,  // country filter
            $city,     // city filter
            $isBreaking, // is_breaking_news filter
        ]);
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return $value === null ? null : (string) $value;
    }
}
