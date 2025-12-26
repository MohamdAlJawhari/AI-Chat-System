<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Services\ArchiveRagService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* 
Message endpoints: list messages, 
                create message (non-stream), 
                and streaming endpoint that proxies model output while persisting messages. 
*/
class MessageController extends Controller
{
    private function assertOwns(Chat $chat): void
    {
        if ($chat->user_id !== (int) (Auth::id() ?? 0))
            abort(403);
    }

    /**
     * Optionally run archive retrieval augmented generation.
     *
     * @param  array<int, array<string, string>>  $messages
     * @return array{context:?string,sources:array<int,array<string,mixed>>}
     */
    private function attachArchiveContext(bool $enabled, ?string $query, array &$messages, array $filters = []): array
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

            // Put archive block right after the first system prompt if it exists
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
     * @return array{name:string,system:string,overrides:array<string,mixed>}
     */
    private function resolvePersona(Chat $chat): array
    {
        $personaConfig = config('llm.personas', []);
        $allowed = $personaConfig['allowed'] ?? [];
        $defaultName = (string) config('llm.default_persona', 'assistant');
        $requested = (string) data_get($chat->settings, 'persona', '');
        $name = in_array($requested, $allowed, true) ? $requested : $defaultName;

        $persona = $personaConfig[$name] ?? [];
        $fallback = $personaConfig[$defaultName] ?? [];

        $system = (string) ($persona['system'] ?? ($fallback['system'] ?? 'You are UChat, a helpful media assistant. Be concise and accurate.'));
        $overrides = is_array($persona['overrides'] ?? null) ? $persona['overrides'] : [];

        return [
            'name' => $name,
            'system' => $system,
            'overrides' => $overrides,
        ];
    }

    /**
     * Merge persona overrides with default LLM options for the streaming payload (excludes HTTP-only keys).
     */
    private function buildLlmOptions(array $overrides = []): array
    {
        $defaults = is_array(config('llm.defaults')) ? config('llm.defaults') : [];
        $merged = array_merge($defaults, $overrides);
        $allowedKeys = ['temperature', 'top_p', 'top_k', 'repeat_penalty', 'num_ctx', 'seed'];

        return array_filter($merged, function ($value, $key) use ($allowedKeys) {
            return in_array($key, $allowedKeys, true) && $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
    }

    // Return messages for a chat (oldest first)
    public function index(Chat $chat)
    {
        $this->assertOwns($chat);

        $messages = $chat->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'metadata', 'created_at']);

        return response()->json($messages);
    }

    /**
     * Normalize archive filters from the request for use in hybrid search and metadata.
     *
     * @return array{search: array<string, mixed>, metadata: array<string, mixed>}
     */
    private function normalizeArchiveFilters(Request $request): array
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

    // Create a message and, for user role, append an assistant reply via the LLM
    public function store(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'uuid', Rule::exists('chats', 'id')],
            'role' => ['required', 'string', Rule::in(['user', 'assistant', 'system', 'tool'])],
            'content' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'archive_search' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.category' => ['nullable', 'string'],
            'filters.country' => ['nullable', 'string'],
            'filters.city' => ['nullable', 'string'],
            'filters.date_from' => ['nullable', 'string'],
            'filters.date_to' => ['nullable', 'string'],
            'filters.is_breaking_news' => ['nullable'],
        ]);

        $useArchive = $request->boolean('archive_search');
        $incomingContent = $request->input('content');
        $filters = $this->normalizeArchiveFilters($request);
        $searchFilters = $filters['search'];
        $filtersForMetadata = $filters['metadata'];

        $userMetadata = $request->input('metadata', []);
        if (!is_array($userMetadata)) {
            $userMetadata = [];
        }
        if ($useArchive) {
            $userMetadata['archive_search'] = true;
            if (!empty($filtersForMetadata)) {
                $userMetadata['archive_filters'] = $filtersForMetadata;
            }
        }

        // 1) Save incoming message
        $msg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => $request->role === 'user' ? (int) (Auth::id() ?? 0) : null,
            'role' => $request->role,
            'content' => $request->input('content'),
            'metadata' => !empty($userMetadata) ? $userMetadata : null,
        ]);

        // If it's assistant/system/tool, don't call the model
        if ($request->role !== 'user') {
            return response()->json($msg, 201);
        }

        // 2) Build short history + system prompt
        $chat = Chat::findOrFail($request->chat_id);

        $this->assertOwns($chat);

        // Title will be generated after the assistant reply based on the conversation start

        $history = $chat->messages()->orderBy('created_at')->take(20)->get();
        $messages = [];

        $persona = $this->resolvePersona($chat);
        if ($persona['system'] !== '') {
            $messages[] = ['role' => 'system', 'content' => $persona['system']];
        }

        $rag = $this->attachArchiveContext($useArchive, $incomingContent, $messages, $searchFilters);
        $ragSources = $rag['sources'] ?? [];

        foreach ($history as $m) {
            if ($m->content === null)
                continue;
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        // 3) Call local LLM (Ollama)
        $modelFromSettings = data_get($chat->settings, 'model'); // optional override per chat
        $assistantText = app(\App\Services\LlmClient::class)->chat($messages, $modelFromSettings, $persona['overrides']);

        // 4) Save assistant reply
        $assistant = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $chat->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => $assistantText,
            'metadata' => array_filter([
                'model' => trim($modelFromSettings ?? (string) config('llm.model')),
                'archive_search' => $useArchive ?: null,
                'sources' => !empty($ragSources) ? $ragSources : null,
                'filters' => $useArchive ? $filtersForMetadata : null,
                'persona' => $persona['name'] ?? null,
            ], fn($v) => $v !== null && $v !== []),
        ]);

        // Generate title now that we have both sides of the start of the conversation
        if (empty($chat->title)) {
            try {
                $chat->title = \App\Support\Title::generateFromChatStart($chat, $modelFromSettings);
                $chat->save();
            } catch (\Throwable $e) {
                /* ignore */
            }
        }

        return response()->json([
            'user_message' => $msg,
            'assistant_message' => $assistant,
        ], 201);
    }

    /*
    Streaming variant used by the UI:
        - Saves the user message
        - Emits SSE {delta} chunks until {done}
        - Persists the assistant message on completion
    */
    public function storeStream(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'uuid', Rule::exists('chats', 'id')],
            'role' => ['required', 'string', Rule::in(['user'])],
            'content' => ['required', 'string'],
            'archive_search' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.category' => ['nullable', 'string'],
            'filters.country' => ['nullable', 'string'],
            'filters.city' => ['nullable', 'string'],
            'filters.date_from' => ['nullable', 'string'],
            'filters.date_to' => ['nullable', 'string'],
            'filters.is_breaking_news' => ['nullable'],
        ]);

        $useArchive = $request->boolean('archive_search');
        $incomingContent = $request->input('content');
        $filters = $this->normalizeArchiveFilters($request);
        $searchFilters = $filters['search'];
        $filtersForMetadata = $filters['metadata'];

        // Save user message
        $userMsg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => (int) (Auth::id() ?? 0),
            'role' => 'user',
            'content' => $request->input('content'),
            'metadata' => $useArchive
                ? array_filter([
                    'archive_search' => true,
                    'archive_filters' => $filtersForMetadata,
                ], fn($v) => $v !== null && $v !== [])
                : null,
        ]);

        $chat = Chat::findOrFail($request->chat_id);

        $this->assertOwns($chat);
        // Defer title generation until after assistant message is persisted (end of stream)

        $history = $chat->messages()->orderBy('created_at')->take(20)->get();
        $messages = [];
        $persona = $this->resolvePersona($chat);
        if ($persona['system'] !== '') {
            $messages[] = ['role' => 'system', 'content' => $persona['system']];
        }

        $rag = $this->attachArchiveContext($useArchive, $incomingContent, $messages, $searchFilters);
        $ragSources = $rag['sources'] ?? [];

        foreach ($history as $m) {
            if ($m->content === null)
                continue;
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        $modelFromSettings = data_get($chat->settings, 'model');
        $base = rtrim((string) config('llm.base_url'), '/');
        $model = trim($modelFromSettings ?? (string) config('llm.model'));
        $llmOptions = $this->buildLlmOptions($persona['overrides']);

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ], $llmOptions);

        $httpRes = \Illuminate\Support\Facades\Http::withOptions(['stream' => true, 'timeout' => 0])
            ->post("$base/api/chat", $payload);

        if ($httpRes->failed()) {
            return response()->json([
                'message' => 'LLM call failed',
                'status' => $httpRes->status(),
                'body' => $httpRes->body(),
            ], $httpRes->status());
        }

        $assistantId = (string) \Illuminate\Support\Str::uuid();

        return response()->stream(function () use ($httpRes, $chat, $assistantId, $model, $ragSources, $useArchive, $filtersForMetadata, $persona) {
            // Continue processing even if client disconnects
            @ignore_user_abort(true);
            $body = $httpRes->toPsrResponse()->getBody();
            $buffer = '';
            $assistantText = '';
            $modelUsed = $model;
            $assistantCreated = false;
            $lastPersistLen = 0;

            $buildAssistantMetadata = function (string $modelName) use ($useArchive, $ragSources, $filtersForMetadata, $persona): array {
                $meta = [
                    'model' => $modelName,
                    'persona' => $persona['name'] ?? null,
                ];
                if ($useArchive) {
                    $meta['archive_search'] = true;
                    if (!empty($ragSources)) {
                        $meta['sources'] = $ragSources;
                    }
                    if (!empty($filtersForMetadata)) {
                        $meta['filters'] = $filtersForMetadata;
                    }
                }
                return $meta;
            };

            $send = function (array $data) {
                echo 'data: ' . json_encode($data) . "\n\n";
                @ob_flush();
                @flush();
            };

            if ($useArchive) {
                $send([
                    'archive_search' => true,
                    'rag_sources' => $ragSources,
                ]);
            }

            while (!$body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk === '' || $chunk === false) {
                    usleep(10000);
                    continue;
                }
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    if ($line === '')
                        continue;
                    $evt = json_decode($line, true);
                    if (!is_array($evt))
                        continue;

                    $delta = data_get($evt, 'message.content', '');
                    if ($delta !== '') {
                        $assistantText .= $delta;
                        $send(['delta' => $delta]);

                        // Create the assistant message in DB on first token, then occasionally update
                        if (!$assistantCreated) {
                            \App\Models\Message::create([
                                'id' => $assistantId,
                                'chat_id' => $chat->id,
                                'user_id' => null,
                                'role' => 'assistant',
                                'content' => $assistantText,
                                'metadata' => $buildAssistantMetadata($modelUsed),
                            ]);
                            $assistantCreated = true;
                            $lastPersistLen = strlen($assistantText);
                        } else {
                            // Persist every ~512 chars to avoid data loss on disconnect
                            if (strlen($assistantText) - $lastPersistLen >= 512) {
                                \App\Models\Message::where('id', $assistantId)->update([
                                    'content' => $assistantText,
                                ]);
                                $lastPersistLen = strlen($assistantText);
                            }
                        }
                    }
                    if (data_get($evt, 'model')) {
                        $modelUsed = (string) data_get($evt, 'model');
                    }
                    if (data_get($evt, 'done') === true) {
                        $send(['done' => true]);
                    }
                }
            }

            if ($assistantCreated) {
                // Final update with full content and model used
                \App\Models\Message::where('id', $assistantId)->update([
                    'content' => $assistantText,
                    'metadata' => $buildAssistantMetadata($modelUsed),
                ]);
            } else {
                // No token streamed (edge case); still create an empty assistant message
                \App\Models\Message::create([
                    'id' => $assistantId,
                    'chat_id' => $chat->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $assistantText,
                    'metadata' => $buildAssistantMetadata($modelUsed),
                ]);
                $assistantCreated = true;
            }

            // Generate a better title from the start of the conversation if still empty
            if (empty($chat->title)) {
                try {
                    $chat->title = \App\Support\Title::generateFromChatStart($chat, $modelUsed);
                    $chat->save();
                } catch (\Throwable $e) { /* ignore */
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
