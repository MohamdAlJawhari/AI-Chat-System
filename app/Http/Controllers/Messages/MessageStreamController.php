<?php

namespace App\Http\Controllers\Messages;

use App\Http\Controllers\Concerns\EnsuresChatOwnership;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Services\MessagePipeline;
use App\Support\Title;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class MessageStreamController extends Controller
{
    use EnsuresChatOwnership;

    public function __construct(private MessagePipeline $pipeline)
    {
    }

    /**
     * Streaming variant:
     *  - Saves the user message
     *  - Emits SSE {delta} chunks until {done}
     *  - Persists the assistant message on completion
     */
    public function stream(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'uuid', Rule::exists('chats', 'id')],
            'role' => ['required', 'string', Rule::in(['user'])],
            'content' => ['required', 'string'],
            'archive_search' => ['nullable', 'boolean'],
            'archive_mode' => ['nullable', 'string', Rule::in(['on', 'off', 'auto'])],
            'auto_filters' => ['nullable', 'boolean'],
            'auto_weights' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.category' => ['nullable', 'string'],
            'filters.country' => ['nullable', 'string'],
            'filters.city' => ['nullable', 'string'],
            'filters.date_from' => ['nullable', 'string'],
            'filters.date_to' => ['nullable', 'string'],
            'filters.is_breaking_news' => ['nullable'],
            'weights' => ['nullable', 'array'],
            'weights.alpha' => ['nullable', 'numeric'],
            'weights.beta' => ['nullable', 'numeric'],
        ]);

        @ignore_user_abort(true);
        @set_time_limit(0);

        $incomingContent = $request->input('content');
        $chat = Chat::findOrFail($request->chat_id);
        $this->assertOwns($chat);

        $archiveDecision = $this->pipeline->resolveArchiveDecision($request, $incomingContent);
        $archiveMode = $archiveDecision['mode'] ?? 'off';
        $useArchive = (bool) ($archiveDecision['use'] ?? false);
        $decisionMeta = is_array($archiveDecision['decision'] ?? null) ? $archiveDecision['decision'] : null;

        $filters = $this->pipeline->normalizeArchiveFilters($request, $useArchive);
        $searchFilters = $filters['search'];
        $filtersForMetadata = $filters['metadata'];
        $weights = $filters['weights'] ?? [];
        $autoMeta = $filters['auto'] ?? [];

        // Save user message
        $userMeta = array_filter([
            'archive_mode' => $archiveMode !== 'off' ? $archiveMode : null,
            'archive_auto' => $archiveMode === 'auto' ? true : null,
            'archive_auto_selected' => $archiveMode === 'auto' ? $useArchive : null,
            'archive_auto_reason' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'reason') ?: null) : null,
            'archive_auto_source' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'source') ?: null) : null,
            'archive_search' => $useArchive ? true : null,
            'archive_filters' => $useArchive ? $filtersForMetadata : null,
            'archive_weights' => $useArchive && !empty($weights) ? $weights : null,
            'archive_filters_auto' => $useArchive && data_get($autoMeta, 'filters.selected') ? true : null,
            'archive_weights_auto' => $useArchive && data_get($autoMeta, 'weights.selected') ? true : null,
            'archive_filters_reason' => $useArchive ? (data_get($autoMeta, 'filters.reason') ?: null) : null,
            'archive_filters_source' => $useArchive ? (data_get($autoMeta, 'filters.source') ?: null) : null,
            'archive_weights_reason' => $useArchive ? (data_get($autoMeta, 'weights.reason') ?: null) : null,
            'archive_weights_source' => $useArchive ? (data_get($autoMeta, 'weights.source') ?: null) : null,
        ], fn($v) => $v !== null && $v !== []);

        $userMsg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => (int) (Auth::id() ?? 0),
            'role' => 'user',
            'content' => $incomingContent,
            'metadata' => !empty($userMeta) ? $userMeta : null,
        ]);

        $history = $chat->messages()->orderBy('created_at')->take(20)->get();
        $messages = [];
        $persona = $this->pipeline->resolvePersona($chat, $incomingContent);
        if ($persona['system'] !== '') {
            $messages[] = ['role' => 'system', 'content' => $persona['system']];
        }

        $rag = $this->pipeline->attachArchiveContext($useArchive, $incomingContent, $messages, $searchFilters, $weights);
        $ragSources = $rag['sources'] ?? [];
        $ragQuery = is_string($rag['query'] ?? null) ? trim((string) $rag['query']) : null;
        $ragQueryOriginal = is_string($rag['query_original'] ?? null) ? trim((string) $rag['query_original']) : null;
        $ragQueryRewrite = is_array($rag['query_rewrite'] ?? null) ? $rag['query_rewrite'] : null;
        if ($ragQueryRewrite && !data_get($ragQueryRewrite, 'used')) {
            $ragQueryRewrite = null;
        }

        foreach ($history as $m) {
            if ($m->content === null) {
                continue;
            }
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        $modelFromSettings = data_get($chat->settings, 'model');
        $base = rtrim((string) config('llm.base_url'), '/');
        $model = trim($modelFromSettings ?? (string) config('llm.model'));
        $llmOptions = $this->pipeline->buildLlmOptions($persona['overrides']);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ];
        if (!empty($llmOptions)) {
            $payload['options'] = $llmOptions;
        }

        $pauseKey = (string) config('rag.summary.pause_key', 'chat.active');
        $pauseTtl = (int) config('rag.summary.pause_ttl', 600);
        if ($pauseKey !== '') {
            Cache::put($pauseKey, true, $pauseTtl);
        }

        try {
            $httpRes = \Illuminate\Support\Facades\Http::withOptions(['stream' => true, 'timeout' => 0])
                ->post("$base/api/chat", $payload);
        } catch (\Throwable $e) {
            if ($pauseKey !== '') {
                Cache::forget($pauseKey);
            }
            return response()->json([
                'message' => 'LLM call failed',
                'error' => $e->getMessage(),
            ], 502);
        }

        if ($httpRes->failed()) {
            if ($pauseKey !== '') {
                Cache::forget($pauseKey);
            }
            return response()->json([
                'message' => 'LLM call failed',
                'status' => $httpRes->status(),
                'body' => $httpRes->body(),
            ], $httpRes->status());
        }

        $assistantId = (string) \Illuminate\Support\Str::uuid();

        return response()->stream(function () use ($httpRes, $chat, $assistantId, $model, $ragSources, $ragQuery, $ragQueryOriginal, $ragQueryRewrite, $useArchive, $archiveMode, $decisionMeta, $filtersForMetadata, $weights, $autoMeta, $persona, $pauseKey) {
            @ignore_user_abort(true);
            @set_time_limit(0); // avoid PHP max_execution_time cutting long streams
            try {
                $body = $httpRes->toPsrResponse()->getBody();
                $buffer = '';
                $assistantText = '';
                $modelUsed = $model;
                $lastModelSent = $modelUsed;
                $assistantCreated = false;
                $lastPersistLen = 0;

                $buildAssistantMetadata = function (string $modelName) use ($useArchive, $archiveMode, $decisionMeta, $ragSources, $ragQuery, $ragQueryOriginal, $ragQueryRewrite, $filtersForMetadata, $weights, $autoMeta, $persona): array {
                    $meta = [
                        'model' => $modelName,
                        'persona' => $persona['name'] ?? null,
                        'persona_requested' => $persona['requested'] ?? null,
                        'persona_reason' => $persona['reason'] ?? null,
                        'persona_auto' => !empty($persona['auto_selected']) ? true : null,
                        'persona_source' => $persona['source'] ?? null,
                        'archive_mode' => $archiveMode !== 'off' ? $archiveMode : null,
                        'archive_auto' => $archiveMode === 'auto' ? true : null,
                        'archive_auto_selected' => $archiveMode === 'auto' ? $useArchive : null,
                        'archive_auto_reason' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'reason') ?: null) : null,
                        'archive_auto_source' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'source') ?: null) : null,
                    ];
                    if ($useArchive) {
                        $meta['archive_search'] = true;
                        if (!empty($ragSources)) {
                            $meta['sources'] = $ragSources;
                        }
                        if (!empty($ragQuery)) {
                            $meta['query'] = $ragQuery;
                        }
                        if (!empty($ragQueryOriginal)) {
                            $meta['query_original'] = $ragQueryOriginal;
                        }
                        if (!empty($ragQueryRewrite)) {
                            $meta['query_rewrite'] = $ragQueryRewrite;
                        }
                        if (!empty($filtersForMetadata)) {
                            $meta['filters'] = $filtersForMetadata;
                        }
                        if (!empty($weights)) {
                            $meta['weights'] = $weights;
                        }
                        if (data_get($autoMeta, 'filters.selected')) {
                            $meta['filters_auto'] = true;
                            $meta['filters_reason'] = data_get($autoMeta, 'filters.reason') ?: null;
                            $meta['filters_source'] = data_get($autoMeta, 'filters.source') ?: null;
                        }
                        if (data_get($autoMeta, 'weights.selected')) {
                            $meta['weights_auto'] = true;
                            $meta['weights_reason'] = data_get($autoMeta, 'weights.reason') ?: null;
                            $meta['weights_source'] = data_get($autoMeta, 'weights.source') ?: null;
                        }
                    }
                    return array_filter($meta, fn($v) => $v !== null && $v !== []);
                };

                $send = function (array $data) {
                    echo 'data: ' . json_encode($data) . "\n\n";
                    @ob_flush();
                    @flush();
                };

                $initialMeta = [
                    'model' => $modelUsed,
                    'archive_search' => $useArchive ? true : null,
                    'archive_mode' => $archiveMode !== 'off' ? $archiveMode : null,
                    'archive_auto' => $archiveMode === 'auto' ? true : null,
                    'archive_auto_selected' => $archiveMode === 'auto' ? $useArchive : null,
                    'archive_auto_reason' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'reason') ?: null) : null,
                    'archive_auto_source' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'source') ?: null) : null,
                    'rag_sources' => $ragSources,
                    'query' => $ragQuery ?: null,
                    'query_original' => $ragQueryOriginal ?: null,
                    'query_rewrite' => $ragQueryRewrite ?: null,
                    'filters' => !empty($filtersForMetadata) ? $filtersForMetadata : null,
                    'weights' => !empty($weights) ? $weights : null,
                    'filters_auto' => data_get($autoMeta, 'filters.selected') ? true : null,
                    'weights_auto' => data_get($autoMeta, 'weights.selected') ? true : null,
                ];
                $initialMeta = array_filter($initialMeta, fn($v) => $v !== null && $v !== []);
                if (!empty($initialMeta)) {
                    $send($initialMeta);
                }
                $send(array_filter([
                    'persona' => $persona['name'] ?? null,
                    'persona_requested' => $persona['requested'] ?? null,
                    'persona_reason' => $persona['reason'] ?? null,
                    'persona_auto' => $persona['auto_selected'] ?? null,
                    'persona_source' => $persona['source'] ?? null,
                ], fn($v) => $v !== null && $v !== []));

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
                        if ($line === '') {
                            continue;
                        }
                        $evt = json_decode($line, true);
                        if (!is_array($evt)) {
                            continue;
                        }

                        $delta = data_get($evt, 'message.content', '');
                        if ($delta !== '') {
                            $assistantText .= $delta;
                            $send(['delta' => $delta]);

                            if (!$assistantCreated) {
                                Message::create([
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
                                if (strlen($assistantText) - $lastPersistLen >= 512) {
                                    Message::where('id', $assistantId)->update([
                                        'content' => $assistantText,
                                    ]);
                                    $lastPersistLen = strlen($assistantText);
                                }
                            }
                        }
                        if (data_get($evt, 'model')) {
                            $modelUsed = (string) data_get($evt, 'model');
                            if ($modelUsed !== $lastModelSent) {
                                $send(['model' => $modelUsed]);
                                $lastModelSent = $modelUsed;
                            }
                        }
                        if (data_get($evt, 'done') === true) {
                            $send(['done' => true]);
                        }
                    }
                }

                if ($assistantCreated) {
                    Message::where('id', $assistantId)->update([
                        'content' => $assistantText,
                        'metadata' => $buildAssistantMetadata($modelUsed),
                    ]);
                } else {
                    Message::create([
                        'id' => $assistantId,
                        'chat_id' => $chat->id,
                        'user_id' => null,
                        'role' => 'assistant',
                        'content' => $assistantText,
                        'metadata' => $buildAssistantMetadata($modelUsed),
                    ]);
                    $assistantCreated = true;
                }

                if (empty($chat->title)) {
                    try {
                        $chat->title = Title::generateFromChatStart($chat, $modelUsed);
                        $chat->save();
                    } catch (\Throwable $e) { /* ignore */ }
                }
            } finally {
                if ($pauseKey !== '') {
                    Cache::forget($pauseKey);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
