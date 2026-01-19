<?php

namespace App\Services\ArchiveRouting\Filters;

use App\Services\ArchiveRouting\RouterClient;

class WeightsRouter
{
    public function __construct(private readonly RouterClient $client)
    {
    }

    /**
     * @return array{weights:array<string,mixed>,used:bool}
     */
    public function route(string $content, float $defaultAlpha, float $defaultBeta, string $model, array $options): array
    {
        $prompt = $this->buildPrompt($content, $defaultAlpha, $defaultBeta);
        $parsed = $this->client->call($prompt, $model, $options);
        $weights = [];

        if (is_array($parsed)) {
            if (array_key_exists('alpha', $parsed)) {
                $weights['alpha'] = $parsed['alpha'];
            }
            if (array_key_exists('beta', $parsed)) {
                $weights['beta'] = $parsed['beta'];
            }
        }

        return [
            'weights' => $weights,
            'used' => is_array($parsed),
        ];
    }

    private function buildPrompt(string $content, float $defaultAlpha, float $defaultBeta): string
    {
        return <<<PROMPT
You select alpha/beta weights for a newsroom archive search.
Return ONLY valid JSON:
{"alpha": number|null, "beta": number|null}

Rules:
- alpha and beta must be between 0 and 1.
- Use null to keep defaults (alpha={$defaultAlpha}, beta={$defaultBeta}).
- Only set values if explicitly requested by the user.

User query:
{$content}
PROMPT;
    }
}
