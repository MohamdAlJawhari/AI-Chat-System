<?php

namespace App\Services;

use App\Services\ArchiveRouting\RouterClient;

class ArchiveUseDecider
{
    public function __construct(private readonly RouterClient $client)
    {
    }

    /**
     * @return array{use_archive:bool,used:bool,reason:string,source:string}
     */
    public function decide(string $content, array $overrides = []): array
    {
        $content = trim($content);
        if ($content === '') {
            return [
                'use_archive' => false,
                'used' => false,
                'reason' => 'Auto archive skipped: empty input',
                'source' => 'skip',
            ];
        }

        $enabled = (bool) config('rag.auto_archive.enabled', true);
        if (!$enabled) {
            return [
                'use_archive' => false,
                'used' => false,
                'reason' => 'Auto archive disabled',
                'source' => 'skip',
            ];
        }

        $model = $this->resolveModel($overrides);
        $options = $this->decisionOptions();
        $prompt = $this->buildPrompt($content);
        $parsed = $this->client->call($prompt, $model, $options);
        $decision = $this->extractDecision($parsed);

        if ($decision === null) {
            return [
                'use_archive' => false,
                'used' => false,
                'reason' => 'Auto archive fallback: LLM unavailable',
                'source' => 'auto-fallback',
            ];
        }

        $reason = $this->extractReason($parsed);
        if ($reason === '') {
            $reason = $decision ? 'Auto archive: use archive' : 'Auto archive: skip archive';
        }

        return [
            'use_archive' => $decision,
            'used' => is_array($parsed),
            'reason' => $reason,
            'source' => is_array($parsed) ? 'auto-llm' : 'auto-fallback',
        ];
    }

    private function resolveModel(array $overrides): string
    {
        $modelOverride = $overrides['model'] ?? config('rag.auto_archive.model');
        return trim((string) ($modelOverride ?: config('llm.model')));
    }

    /**
     * @return array<string,mixed>
     */
    private function decisionOptions(): array
    {
        return [
            'temperature' => 0.0,
            'top_p' => 0.1,
            'repeat_penalty' => 1.0,
            'http_timeout' => (int) config('rag.auto_archive.http_timeout', 8),
        ];
    }

    private function buildPrompt(string $content): string
    {
        return <<<PROMPT
You decide whether the user's request requires searching the newsroom archive.

Return ONLY valid JSON:
{"use_archive": true|false, "reason": string}

Rules:
- Return true when the answer should be grounded in the archive (news events, dates, reports, sources).
- Return false when the request can be answered without the archive (general knowledge, opinion, creative).
- If unsure, return false.

User request:
{$content}
PROMPT;
    }

    /**
     * @param  array<string,mixed>|null  $parsed
     */
    private function extractDecision(?array $parsed): ?bool
    {
        if (!is_array($parsed)) {
            return null;
        }
        $value = $parsed['use_archive'] ?? $parsed['useArchive'] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', 'yes', 'on', '1'], true)) {
                return true;
            }
            if (in_array($normalized, ['false', 'no', 'off', '0'], true)) {
                return false;
            }
        }
        return null;
    }

    /**
     * @param  array<string,mixed>|null  $parsed
     */
    private function extractReason(?array $parsed): string
    {
        if (!is_array($parsed) || !array_key_exists('reason', $parsed)) {
            return '';
        }
        $raw = trim((string) $parsed['reason']);
        return $raw;
    }
}
