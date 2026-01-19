<?php

namespace App\Services\ArchiveRouting\Filters;

use App\Services\ArchiveRouting\RouterClient;

class BreakingFilterRouter
{
    public function __construct(private readonly RouterClient $client)
    {
    }

    /**
     * @return array{value:mixed,used:bool}
     */
    public function route(string $content, string $model, array $options): array
    {
        $prompt = $this->buildPrompt($content);
        $parsed = $this->client->call($prompt, $model, $options);
        $value = null;

        if (is_array($parsed) && array_key_exists('value', $parsed)) {
            $value = $parsed['value'];
        }

        return [
            'value' => $value,
            'used' => is_array($parsed),
        ];
    }

    private function buildPrompt(string $content): string
    {
        return <<<PROMPT
You select the breaking news filter for a newsroom archive search.
Return ONLY valid JSON:
{"value": true|false|null}

Rules:
- Return true only if the user explicitly wants breaking news only.
- Return false only if the user explicitly wants to exclude breaking news.
- Otherwise return null.

User query:
{$content}
PROMPT;
    }
}
