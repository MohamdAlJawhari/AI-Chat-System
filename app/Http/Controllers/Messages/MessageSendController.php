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
        $filters = $this->pipeline->normalizeArchiveFilters($request);
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

        $rag = $this->pipeline->attachArchiveContext($useArchive, $incomingContent, $messages, $searchFilters);
        $ragSources = $rag['sources'] ?? [];

        foreach ($history as $m) {
            if ($m->content === null) {
                continue;
            }
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

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
                'sources' => !empty($ragSources) ? $ragSources : null,
                'filters' => $useArchive ? $filtersForMetadata : null,
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

        return response()->json([
            'user_message' => $msg,
            'assistant_message' => $assistant,
        ], 201);
    }
}

