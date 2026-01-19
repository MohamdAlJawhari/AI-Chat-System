<?php

namespace App\Services\ArchiveRouting\Filters;

use App\Services\ArchiveRouting\AllowedFormatter;
use App\Services\ArchiveRouting\RouterClient;

class DateToFilterRouter
{
    public function __construct(
        private readonly RouterClient $client,
        private readonly AllowedFormatter $formatter,
    ) {
    }

    /**
     * @param  array{min?:string|null,max?:string|null}|null  $range
     * @return array{value:?string,used:bool}
     */
    public function route(string $content, ?array $range, string $model, array $options): array
    {
        $rangeLabel = $this->formatter->formatDateRange($range);
        $prompt = $this->buildPrompt($content, $rangeLabel);
        $parsed = $this->client->call($prompt, $model, $options);

        return [
            'value' => $this->extractValue($parsed),
            'used' => is_array($parsed),
        ];
    }

    private function buildPrompt(string $content, string $dateRange): string
    {
        return <<<PROMPT
You select the end date (date_to) filter for a newsroom archive search.
Return ONLY valid JSON:
{"value": "YYYY-MM-DD"|null}

Rules:
- Only set a date if it is explicit in the user query.
- Available archive date range: {$dateRange}. If outside the range, return null.
- Use YYYY-MM-DD format.

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
