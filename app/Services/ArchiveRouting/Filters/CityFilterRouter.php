<?php

namespace App\Services\ArchiveRouting\Filters;

use App\Services\ArchiveRouting\AllowedFormatter;
use App\Services\ArchiveRouting\RouterClient;

class CityFilterRouter
{
    public function __construct(
        private readonly RouterClient $client,
        private readonly AllowedFormatter $formatter,
    ) {
    }

    /**
     * @param  array<int,string>  $allowedValues
     * @return array{value:?string,used:bool}
     */
    public function route(string $content, array $allowedValues, string $model, array $options): array
    {
        $max = (int) config('rag.auto_router.max_values', 60);
        $allowedList = $this->formatter->formatAllowed($allowedValues, $max);
        $prompt = $this->buildPrompt($content, $allowedList);
        $parsed = $this->client->call($prompt, $model, $options);

        return [
            'value' => $this->extractValue($parsed),
            'used' => is_array($parsed),
        ];
    }

    private function buildPrompt(string $content, string $allowedList): string
    {
        return <<<PROMPT
You select the best City filter for a newsroom archive search.
Return ONLY valid JSON:
{"value": string|null}

Rules:
- Choose at most one value from the allowed list; if no match, return null.
- Do NOT invent values.

Allowed City values: {$allowedList}

User query:
{$content}
PROMPT;
    }

    /**
     * @param  array<string,mixed>|null  $parsed
     */
    private function extractValue(?array $parsed): ?string
    {
        if (!is_array($parsed) || !array_key_exists('value', $parsed)) {
            return null;
        }

        $raw = $parsed['value'];
        if ($raw === null || $raw === '') {
            return null;
        }

        return trim((string) $raw);
    }
}
