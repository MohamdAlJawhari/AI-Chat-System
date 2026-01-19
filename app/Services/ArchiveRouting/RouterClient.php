<?php

namespace App\Services\ArchiveRouting;

use App\Services\LlmClient;

class RouterClient
{
    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function call(string $prompt, string $model, array $options): ?array
    {
        try {
            $raw = trim($this->llm->chat([
                ['role' => 'system', 'content' => 'You are a strict classifier that returns only valid JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ], $model, $options));
        } catch (\Throwable) {
            return null;
        }

        return $this->parseJson($raw);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parseJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```[a-zA-Z]*\s*/', '', $raw) ?? $raw;
            $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
            $raw = trim($raw);
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $raw, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
