<?php

namespace App\Services;

use App\Models\Chat;
use App\Services\ArchiveRagService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessagePipeline
{
    /**
     * Normalize archive filters from the request for use in hybrid search and metadata.
     *
     * @return array{search: array<string, mixed>, metadata: array<string, mixed>}
     */
    public function normalizeArchiveFilters(Request $request): array
    {
        $filtersInput = $request->input('filters', []);
        if (!is_array($filtersInput)) {
            $filtersInput = [];
        }

        $category = trim((string) ($filtersInput['category'] ?? ''));
        $country = trim((string) ($filtersInput['country'] ?? ''));
        $city = trim((string) ($filtersInput['city'] ?? ''));

        $dateFromRaw = trim((string) ($filtersInput['date_from'] ?? ''));
        $dateToRaw = trim((string) ($filtersInput['date_to'] ?? ''));
        $dateFrom = null;
        $dateTo = null;

        if ($dateFromRaw !== '') {
            try {
                $dateFrom = CarbonImmutable::parse($dateFromRaw)->startOfDay();
                $dateFromRaw = $dateFrom->toDateString();
            } catch (\Throwable) {
                $dateFromRaw = '';
                $dateFrom = null;
            }
        }

        if ($dateToRaw !== '') {
            try {
                $dateTo = CarbonImmutable::parse($dateToRaw)->endOfDay();
                $dateToRaw = $dateTo->toDateString();
            } catch (\Throwable) {
                $dateToRaw = '';
                $dateTo = null;
            }
        }

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            [$dateFromRaw, $dateToRaw] = [$dateFrom->toDateString(), $dateTo->toDateString()];
        }

        $rawBreaking = $filtersInput['is_breaking_news'] ?? '';
        $normalizedBreaking = is_string($rawBreaking) ? strtolower(trim($rawBreaking)) : $rawBreaking;
        $isBreaking = null;
        if ($normalizedBreaking !== '' && $normalizedBreaking !== null) {
            $truthy = ['1', 1, true, 'true', 'yes', 'on'];
            $falsy = ['0', 0, false, 'false', 'no', 'off'];

            if (in_array($normalizedBreaking, $truthy, true)) {
                $isBreaking = true;
            } elseif (in_array($normalizedBreaking, $falsy, true)) {
                $isBreaking = false;
            }
        }

        $metadataFilters = [
            'category' => $category !== '' ? $category : null,
            'country' => $country !== '' ? $country : null,
            'city' => $city !== '' ? $city : null,
            'date_from' => $dateFromRaw !== '' ? $dateFromRaw : null,
            'date_to' => $dateToRaw !== '' ? $dateToRaw : null,
            'is_breaking_news' => $isBreaking,
        ];

        $searchFilters = [
            'category' => $metadataFilters['category'],
            'country' => $metadataFilters['country'],
            'city' => $metadataFilters['city'],
            'date_from' => $dateFrom?->toIso8601String(),
            'date_to' => $dateTo?->toIso8601String(),
            'is_breaking_news' => $isBreaking,
        ];

        return [
            'search' => $searchFilters,
            'metadata' => array_filter($metadataFilters, fn($v) => $v !== null),
        ];
    }

    /**
     * Optionally run archive retrieval augmented generation and inject context.
     *
     * @param  array<int, array<string, string>>  $messages
     * @return array{context:?string,sources:array<int,array<string,mixed>>}
     */
    public function attachArchiveContext(bool $enabled, ?string $query, array &$messages, array $filters = []): array
    {
        if (!$enabled) {
            return ['context' => null, 'sources' => []];
        }

        $query = trim((string) $query);
        if ($query === '') {
            return ['context' => null, 'sources' => []];
        }

        $rag = app(ArchiveRagService::class)->buildContext($query, null, $filters);
        if (!empty($rag['context'])) {
            $archiveMsg = ['role' => 'system', 'content' => $rag['context']];

            if (!empty($messages) && ($messages[0]['role'] ?? null) === 'system') {
                array_splice($messages, 1, 0, [$archiveMsg]);
            } else {
                array_unshift($messages, $archiveMsg);
            }
        }

        return $rag;
    }

    /**
     * Resolve the active persona (name, system prompt, overrides) for a chat.
     *
     * @return array{name:string,system:string,overrides:array<string,mixed>,requested:?string,auto_selected:bool,reason:?string,source:?string}
     */
    public function resolvePersona(Chat $chat, ?string $incomingContent = null): array
    {
        $personaConfig = config('llm.personas', []);
        $allowed = $personaConfig['allowed'] ?? [];
        $defaultName = (string) config('llm.default_persona', 'assistant');
        $requested = (string) data_get($chat->settings, 'persona', '');
        $requestedName = in_array($requested, $allowed, true) ? $requested : $defaultName;

        $autoRequested = $requestedName === 'auto';
        $candidateAllowed = array_values(array_filter($allowed, fn($n) => $n !== 'auto'));

        $decision = [
            'name' => $autoRequested ? $defaultName : $requestedName,
            'reason' => null,
            'source' => $autoRequested ? 'auto-llm' : 'requested',
        ];

        if ($autoRequested) {
            $llmDecision = $this->llmRoutePersona(
                $incomingContent,
                $candidateAllowed,
                $defaultName,
                $personaConfig,
                (string) data_get($chat->settings, 'model')
            );
            $decision = array_merge($decision, $llmDecision, ['source' => 'auto-llm']);

            if (!in_array($decision['name'], $candidateAllowed, true)) {
                $keywordDecision = $this->routePersona($incomingContent, $candidateAllowed, $defaultName, $personaConfig['router'] ?? []);
                $decision = array_merge($decision, $keywordDecision, ['source' => 'auto-keyword']);
            }
        }

        $resolvedName = $decision['name'];
        if ($resolvedName === 'auto' || !in_array($resolvedName, $candidateAllowed, true)) {
            $resolvedName = in_array($defaultName, $candidateAllowed, true) ? $defaultName : ($candidateAllowed[0] ?? $defaultName);
        }

        $persona = $personaConfig[$resolvedName] ?? [];
        $fallback = $personaConfig[$defaultName] ?? [];

        $system = (string) ($persona['system'] ?? ($fallback['system'] ?? 'You are UChat, a helpful media assistant. Be concise and accurate.'));
        $overrides = is_array($persona['overrides'] ?? null) ? $persona['overrides'] : [];

        return [
            'name' => $resolvedName,
            'requested' => $autoRequested ? 'auto' : ($requested !== '' ? $requested : null),
            'auto_selected' => $autoRequested,
            'reason' => $decision['reason'] ?? null,
            'source' => $decision['source'] ?? null,
            'system' => $system,
            'overrides' => $overrides,
        ];
    }

    /**
     * Merge persona overrides with default LLM options for the streaming payload (excludes HTTP-only keys).
     */
    public function buildLlmOptions(array $overrides = []): array
    {
        $defaults = is_array(config('llm.defaults')) ? config('llm.defaults') : [];
        $merged = array_merge($defaults, $overrides);
        $allowedKeys = ['temperature', 'top_p', 'top_k', 'repeat_penalty', 'num_ctx', 'seed'];

        return array_filter($merged, function ($value, $key) use ($allowedKeys) {
            return in_array($key, $allowedKeys, true) && $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * LLM-based persona router: asks the model to pick the best persona for the user's prompt.
     *
     * @param  array<int,string>  $allowedNames  personas eligible for routing (excluding "auto")
     * @return array{name:string,reason:string}
     */
    private function llmRoutePersona(?string $incomingContent, array $allowedNames, string $defaultName, array $personaConfig = [], ?string $modelOverride = null): array
    {
        $content = trim((string) $incomingContent);
        $fallback = in_array($defaultName, $allowedNames, true) ? $defaultName : ($allowedNames[0] ?? $defaultName);

        if ($content === '' || empty($allowedNames)) {
            return ['name' => $fallback, 'reason' => 'LLM router fallback: empty content or no personas'];
        }

        $lines = [];
        foreach ($allowedNames as $name) {
            $desc = (string) data_get($personaConfig, "$name.system", '');
            $desc = Str::limit(preg_replace('/\s+/', ' ', $desc), 220, '…');
            $lines[] = "- {$name}: {$desc}";
        }
        $allowedList = implode("\n", $lines);

        $prompt = <<<PROMPT
You are a router. Choose exactly one persona from the allowed list that best fits the user's request.
Return only the persona name (no punctuation, no quotes, no explanation).

Allowed personas:
{$allowedList}

User request:
{$content}
PROMPT;

        try {
            $model = trim((string) ($modelOverride ?: config('llm.model')));
            $raw = trim(app(\App\Services\LlmClient::class)->chat([
                ['role' => 'system', 'content' => 'You are a strict classifier that returns only the persona name from the provided list.'],
                ['role' => 'user', 'content' => $prompt],
            ], $model, [
                'temperature' => 0.0,
                'top_p' => 0.1,
                'repeat_penalty' => 1.0,
                'http_timeout' => 12,
            ]));

            $normalized = strtolower($raw);
            $normalized = preg_replace('/[^a-z0-9_\\- ]+/', '', $normalized);
            $normalized = str_replace([' ', '-'], '_', $normalized);

            foreach ($allowedNames as $name) {
                if ($normalized === $name) {
                    return ['name' => $name, 'reason' => 'LLM router chose ' . $name];
                }
            }
            foreach ($allowedNames as $name) {
                if (str_contains($normalized, $name)) {
                    return ['name' => $name, 'reason' => 'LLM router matched ' . $name . ' in "' . $raw . '"'];
                }
            }

            return ['name' => $fallback, 'reason' => 'LLM router fallback: unmatched output "' . $raw . '"'];
        } catch (\Throwable $e) {
            return ['name' => $fallback, 'reason' => 'LLM router failed: ' . $e->getMessage()];
        }
    }

    /**
     * Lightweight, keyword-based persona router used as a fallback when LLM routing is unavailable.
     *
     * @param  array<int,string>  $allowed
     * @return array{name:string,reason:string}
     */
    private function routePersona(?string $incomingContent, array $allowed, string $defaultName, array $routerConfig = []): array
    {
        $content = strtolower(trim((string) $incomingContent));
        $fallback = (string) ($routerConfig['fallback'] ?? $defaultName);
        if (!in_array($fallback, $allowed, true)) {
            $fallback = in_array($defaultName, $allowed, true) ? $defaultName : ($allowed[0] ?? $defaultName);
        }

        $rules = is_array($routerConfig['rules'] ?? null) ? $routerConfig['rules'] : [];
        if ($content !== '') {
            foreach ($rules as $persona => $keywords) {
                if (!in_array($persona, $allowed, true)) {
                    continue;
                }
                foreach ((array) $keywords as $kw) {
                    $kw = strtolower(trim((string) $kw));
                    if ($kw === '') {
                        continue;
                    }
                    if (str_contains($content, $kw)) {
                        return [
                            'name' => $persona,
                            'reason' => 'Matched keyword "' . $kw . '"',
                        ];
                    }
                }
            }
        }

        return [
            'name' => $fallback,
            'reason' => $content === '' ? 'No user query to route' : 'No routing rule matched',
        ];
    }
}
