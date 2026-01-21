<?php

namespace App\Services;

use App\Services\ArchiveRouting\RouterClient;

class ArchiveQueryRewriter
{
    public function __construct(private readonly RouterClient $client)
    {
    }

    /**
     * @return array{query:string,used:bool,reason:string,source:string}
     */
    public function rewrite(string $query): array
    {
        $content = trim($query);
        if ($content === '') {
            return [
                'query' => '',
                'used' => false,
                'reason' => 'Query rewrite skipped: empty input',
                'source' => 'skip',
            ];
        }

        $enabled = (bool) config('rag.query_rewrite.enabled', false);
        if (!$enabled) {
            return [
                'query' => $content,
                'used' => false,
                'reason' => 'Query rewrite disabled',
                'source' => 'skip',
            ];
        }

        $maxChars = (int) config('rag.query_rewrite.max_chars', 220);
        if ($maxChars <= 0) {
            $maxChars = 220;
        }

        $model = $this->resolveModel();
        $options = $this->rewriteOptions();

        $prompt = $this->buildPrompt($content, $maxChars);
        $parsed = $this->client->call($prompt, $model, $options);

        $rewritten = $this->extractQuery($parsed);
        if ($rewritten === '') {
            return [
                'query' => $content,
                'used' => false,
                'reason' => 'Query rewrite fallback: empty output',
                'source' => 'auto-fallback',
            ];
        }

        $rewritten = $this->truncate($rewritten, $maxChars);
        if ($rewritten === '') {
            return [
                'query' => $content,
                'used' => false,
                'reason' => 'Query rewrite fallback: empty output',
                'source' => 'auto-fallback',
            ];
        }

        $used = is_array($parsed);
        $same = strcasecmp($rewritten, $content) === 0;

        return [
            'query' => $same ? $content : $rewritten,
            'used' => $used,
            'reason' => $used
                ? ($same ? 'LLM query rewrite kept original' : 'LLM query rewrite applied')
                : 'Query rewrite fallback: LLM unavailable',
            'source' => $used ? 'auto-llm' : 'auto-fallback',
        ];
    }

    private function resolveModel(): string
    {
        $modelOverride = config('rag.query_rewrite.model');
        return trim((string) ($modelOverride ?: config('llm.model')));
    }

    /**
     * @return array<string,mixed>
     */
    private function rewriteOptions(): array
    {
        return [
            'temperature' => 0.0,
            'top_p' => 0.1,
            'repeat_penalty' => 1.0,
            'http_timeout' => (int) config('rag.query_rewrite.http_timeout', 8),
        ];
    }

    private function buildPrompt(string $content, int $maxChars): string
    {
        return <<<PROMPT
            Rewrite the input into a short Arabic news search query.

            Return ONLY valid JSON:
            {"query": string}

            Rules:
            - Same language as input.
            - Convert questions into neutral news topics.
            - Keep locations, actors, dates, and event types.
            - Remove instructions and meta phrases (مثل: اكتب، تكلم، من الأرشيف).
            - Topic-style, not a sentence.
            - Max {$maxChars} characters.

            Input:
            {$content}
            PROMPT;
    }

    /**
     * @param  array<string,mixed>|null  $parsed
     */
    private function extractQuery(?array $parsed): string
    {
        if (!is_array($parsed) || !array_key_exists('query', $parsed)) {
            return '';
        }

        $raw = $parsed['query'];
        if ($raw === null || $raw === '') {
            return '';
        }

        return trim((string) $raw);
    }

    private function truncate(string $text, int $limit): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }
        if ($limit <= 0 || mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit));
    }
}
