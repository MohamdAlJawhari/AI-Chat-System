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

        // Save user message
        $userMsg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => (int) (Auth::id() ?? 0),
            'role' => 'user',
            'content' => $incomingContent,
            'metadata' => $useArchive
                ? array_filter([
                    'archive_search' => true,
                    'archive_filters' => $filtersForMetadata,
                ], fn($v) => $v !== null && $v !== [])
                : null,
        ]);

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
            @ignore_user_abort(true);
            @set_time_limit(0); // avoid PHP max_execution_time cutting long streams
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
                    'persona_requested' => $persona['requested'] ?? null,
                    'persona_reason' => $persona['reason'] ?? null,
                    'persona_auto' => !empty($persona['auto_selected']) ? true : null,
                    'persona_source' => $persona['source'] ?? null,
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
                return array_filter($meta, fn($v) => $v !== null && $v !== []);
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
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
