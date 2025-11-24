<?php

namespace App\Services;

class ArchiveRagService
{
    public function __construct(
        private readonly HybridSearchService $search,
    ) {
    }

    /**
     * Build a context block + metadata for archive-grounded answers.
     *
     * @return array{context:?string,sources:array<int,array<string,mixed>>}
     */
    public function buildContext(string $query, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('rag.doc_limit', 16);
        $results = $this->search->searchDocuments($query, [
            'limit' => $limit,
        ]);

        if (empty($results)) {
            return ['context' => null, 'sources' => []];
        }

        $instruction = trim((string) config('rag.instruction', 'Use these archive excerpts to answer.'));
        $bodyLimit = max(200, (int) config('rag.body_character_limit', 900));

        $sources = [];
        $contextParts = [$instruction, ''];

        foreach ($results as $idx => $row) {
            $title = trim((string) ($row->title ?? ''));
            $intro = trim((string) ($row->introduction ?? ''));
            $snippet = $this->cleanSnippet($row->best_snippet ?? '');
            $bodyExcerpt = $this->truncate($row->body ?? '', $bodyLimit);

            $source = [
                'index' => $idx + 1,
                'news_item_id' => (string) $row->news_item_id,
                'title' => $title !== '' ? $title : 'Untitled dispatch',
                'introduction' => $intro,
                'snippet' => $snippet,
                'body_excerpt' => $bodyExcerpt,
                'score' => round((float) ($row->doc_score ?? 0), 4),
            ];
            $sources[] = $source;

            $segment = '[' . $source['index'] . '] '
                . $source['title']
                . ' (ID: ' . $source['news_item_id'] . ')';
            $lines = [$segment];
            if ($intro !== '') {
                $lines[] = 'Intro: ' . $intro;
            }
            if ($snippet !== '') {
                $lines[] = 'Snippet: ' . $snippet;
            }
            if ($bodyExcerpt !== '') {
                $lines[] = 'Body excerpt: ' . $bodyExcerpt;
            }
            $lines[] = str_repeat('─', 50);
            $contextParts[] = implode("\n", $lines);
        }

        $context = trim(implode("\n", $contextParts));

        return [
            'context' => $context !== '' ? $context : null,
            'sources' => $sources,
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
