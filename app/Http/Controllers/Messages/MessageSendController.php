<?php

namespace App\Http\Controllers\Messages;

use App\Http\Controllers\Concerns\EnsuresChatOwnership;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Services\LlmClient;
use App\Services\MessagePipeline;
use App\Support\Title;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class MessageSendController extends Controller
{
    use EnsuresChatOwnership;

    public function __construct(private MessagePipeline $pipeline)
    {
    }

    /**
     * Create a message (user or assistant/system/tool) and, for user role, append an assistant reply via the LLM.
     */
    public function store(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'uuid', Rule::exists('chats', 'id')],
            'role' => ['required', 'string', Rule::in(['user', 'assistant', 'system', 'tool'])],
            'content' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
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

        $incomingContent = $request->input('content');
        $archiveDecision = $this->pipeline->resolveArchiveDecision($request, $incomingContent);
        $archiveMode = $archiveDecision['mode'] ?? 'off';
        $useArchive = (bool) ($archiveDecision['use'] ?? false);
        $decisionMeta = is_array($archiveDecision['decision'] ?? null) ? $archiveDecision['decision'] : null;

        $filters = $this->pipeline->normalizeArchiveFilters($request, $useArchive);
        $searchFilters = $filters['search'];
        $filtersForMetadata = $filters['metadata'];
        $weights = $filters['weights'] ?? [];
        $autoMeta = $filters['auto'] ?? [];

        $userMetadata = $request->input('metadata', []);
        if (!is_array($userMetadata)) {
            $userMetadata = [];
        }
        if ($archiveMode !== 'off') {
            $userMetadata['archive_mode'] = $archiveMode;
        }
        if ($archiveMode === 'auto') {
            $userMetadata['archive_auto'] = true;
            $userMetadata['archive_auto_selected'] = $useArchive;
            $userMetadata['archive_auto_reason'] = data_get($decisionMeta, 'reason') ?: null;
            $userMetadata['archive_auto_source'] = data_get($decisionMeta, 'source') ?: null;
        }
        if ($useArchive) {
            $userMetadata['archive_search'] = true;
            if (!empty($filtersForMetadata)) {
                $userMetadata['archive_filters'] = $filtersForMetadata;
            }
            if (!empty($weights)) {
                $userMetadata['archive_weights'] = $weights;
            }
            if (data_get($autoMeta, 'filters.selected')) {
                $userMetadata['archive_filters_auto'] = true;
                $userMetadata['archive_filters_reason'] = data_get($autoMeta, 'filters.reason') ?: null;
                $userMetadata['archive_filters_source'] = data_get($autoMeta, 'filters.source') ?: null;
            }
            if (data_get($autoMeta, 'weights.selected')) {
                $userMetadata['archive_weights_auto'] = true;
                $userMetadata['archive_weights_reason'] = data_get($autoMeta, 'weights.reason') ?: null;
                $userMetadata['archive_weights_source'] = data_get($autoMeta, 'weights.source') ?: null;
            }
        }

        // Save incoming message
        $msg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => $request->role === 'user' ? (int) (Auth::id() ?? 0) : null,
            'role' => $request->role,
            'content' => $incomingContent,
            'metadata' => !empty($userMetadata) ? $userMetadata : null,
        ]);

        // If it's assistant/system/tool, don't call the model
        if ($request->role !== 'user') {
            return response()->json($msg, 201);
        }

        // Build short history + system prompt
        $chat = Chat::findOrFail($request->chat_id);
        $this->assertOwns($chat);

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

        $pauseKey = (string) config('rag.summary.pause_key', 'chat.active');
        $pauseTtl = (int) config('rag.summary.pause_ttl', 600);
        if ($pauseKey !== '') {
            Cache::put($pauseKey, true, $pauseTtl);
        }

        try {
            // Call local LLM (Ollama)
            $modelFromSettings = data_get($chat->settings, 'model'); // optional override per chat
            $assistantText = app(LlmClient::class)->chat($messages, $modelFromSettings, $persona['overrides']);

            // Save assistant reply
            $assistant = Message::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'chat_id' => $chat->id,
                'user_id' => null,
                'role' => 'assistant',
                'content' => $assistantText,
                'metadata' => array_filter([
                    'model' => trim($modelFromSettings ?? (string) config('llm.model')),
                    'archive_search' => $useArchive ?: null,
                    'archive_mode' => $archiveMode !== 'off' ? $archiveMode : null,
                    'archive_auto' => $archiveMode === 'auto' ? true : null,
                    'archive_auto_selected' => $archiveMode === 'auto' ? $useArchive : null,
                    'archive_auto_reason' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'reason') ?: null) : null,
                    'archive_auto_source' => $archiveMode === 'auto' ? (data_get($decisionMeta, 'source') ?: null) : null,
                    'sources' => !empty($ragSources) ? $ragSources : null,
                    'query' => $useArchive && !empty($ragQuery) ? $ragQuery : null,
                    'query_original' => $useArchive && !empty($ragQueryOriginal) ? $ragQueryOriginal : null,
                    'query_rewrite' => $useArchive && !empty($ragQueryRewrite) ? $ragQueryRewrite : null,
                    'filters' => $useArchive ? $filtersForMetadata : null,
                    'weights' => $useArchive && !empty($weights) ? $weights : null,
                    'filters_auto' => data_get($autoMeta, 'filters.selected') ? true : null,
                    'filters_reason' => data_get($autoMeta, 'filters.reason') ?: null,
                    'filters_source' => data_get($autoMeta, 'filters.source') ?: null,
                    'weights_auto' => data_get($autoMeta, 'weights.selected') ? true : null,
                    'weights_reason' => data_get($autoMeta, 'weights.reason') ?: null,
                    'weights_source' => data_get($autoMeta, 'weights.source') ?: null,
                    'persona' => $persona['name'] ?? null,
                    'persona_requested' => $persona['requested'] ?? null,
                    'persona_reason' => $persona['reason'] ?? null,
                    'persona_auto' => !empty($persona['auto_selected']) ? true : null,
                    'persona_source' => $persona['source'] ?? null,
                ], fn($v) => $v !== null && $v !== []),
            ]);

            // Generate title now that we have both sides of the start of the conversation
            if (empty($chat->title)) {
                try {
                    $chat->title = Title::generateFromChatStart($chat, $modelFromSettings);
                    $chat->save();
                } catch (\Throwable $e) {
                    /* ignore */
                }
            }
        } finally {
            if ($pauseKey !== '') {
                Cache::forget($pauseKey);
            }
        }

        return response()->json([
            'user_message' => $msg,
            'assistant_message' => $assistant,
        ], 201);
    }
}
