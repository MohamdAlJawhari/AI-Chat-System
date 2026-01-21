<?php

namespace App\Services;

class ArchiveRagService
{
    public function __construct(
        private readonly HybridSearchService $search,
        private readonly ArchiveQueryRewriter $rewriter,
    ) {
    }

    /**
     * Build a context block + metadata for archive-grounded answers.
     *
     * @param  array<string,mixed>  $filters
     * @param  array<string,float>  $weights
     * @return array{context:?string,sources:array<int,array<string,mixed>>,query:string,query_original:string,query_rewrite:array<string,mixed>}
     */
    public function buildContext(string $query, ?int $limit = null, array $filters = [], array $weights = []): array
    {
        $limit = $limit ?? (int) config('rag.doc_limit', 15);
        $originalQuery = trim($query);
        $rewrite = $this->rewriter->rewrite($originalQuery);
        $searchQuery = $rewrite['query'] !== '' ? $rewrite['query'] : $originalQuery;
        $options = [
            'limit' => $limit,
            'filters' => $filters,
        ];
        if (array_key_exists('alpha', $weights)) {
            $options['alpha'] = $weights['alpha'];
        }
        if (array_key_exists('beta', $weights)) {
            $options['beta'] = $weights['beta'];
        }

        $results = $this->search->searchDocuments($searchQuery, $options);

        if (empty($results)) {
            return [
                'context' => null,
                'sources' => [],
                'query' => $searchQuery,
                'query_original' => $originalQuery,
                'query_rewrite' => $rewrite,
            ];
        }

        $instruction = trim((string) config('rag.instruction', ''));
        $rules = $instruction !== '' ? $instruction : implode("\n", [
            "You are a press assistant. Use ONLY the sources inside <<ARCHIVE>>.",
            "Do NOT invent facts, numbers, dates, names, or events not explicitly in sources.",
            "When you use a claim, cite it like [1] or [2].",
            "If the sources are insufficient, say: 'المصادر في الأرشيف غير كافية للإجابة بدقة.' But give answer if possible.",
            "Write a concise answer first, then (optional) a short timeline or bullets if asked.",
        ]);
        $bodyLimit = max(200, (int) config('rag.body_character_limit', 500));

        $sources = [];
        $contextParts = [
            "<<ARCHIVE>>",
            $rules,
            "",
            "SOURCES:",
            ""
        ];

        foreach ($results as $idx => $row) {
            $title = trim((string) ($row->title ?? ''));
            $intro = trim((string) ($row->introduction ?? ''));
            $snippet = $this->cleanSnippet($row->best_snippet ?? '');
            $bodyExcerpt = $this->truncate($row->body ?? '', min($bodyLimit, 350));
            // $bodyExcerpt = $this->truncate($row->body ?? '', $bodyLimit);

            $source = [
                'index' => $idx + 1,
                'news_id' => (string) $row->news_id,
                'title' => $title !== '' ? $title : 'Untitled dispatch',
                // 'introduction' => $intro,
                'snippet' => $snippet,
                'body_excerpt' => $bodyExcerpt,
                'score' => round((float) ($row->doc_score ?? 0), 4),
            ];
            $sources[] = $source;

            $segment = "Source [{$source['index']}] | news_id={$source['news_id']} | score={$source['score']}\n"
                    . "Title: {$source['title']}\n";

            $lines = [$segment];

            if ($snippet !== '') $lines[] = "Snippet: {$snippet}";
            elseif ($intro !== '') $lines[] = "Intro: {$intro}";
            
            if ($bodyExcerpt !== '') $lines[] = "Excerpt: {$bodyExcerpt}";

            $lines[] = "---";
            $contextParts[] = implode("\n", $lines);
        }
        $contextParts[] = "<<END_ARCHIVE>>";

        $context = trim(implode("\n", $contextParts));

        return [
            'context' => $context !== '' ? $context : null,
            'sources' => $sources,
            'query' => $searchQuery,
            'query_original' => $originalQuery,
            'query_rewrite' => $rewrite,
        ];
    }

    private function cleanSnippet(?string $html): string
    {
        $text = strip_tags((string) $html);
        $text = preg_replace('/\s+/', ' ', $text ?? '');
        return trim($text ?? '');
    }

    private function truncate(?string $text, int $limit): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, max(1, $limit - 1))) . '…';
    }
}
