<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
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

    // Return messages for a chat (oldest first)
    public function index(Chat $chat)
    {
        $this->assertOwns($chat);

        $messages = $chat->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'metadata', 'created_at']);

        return response()->json($messages);
    }

    // Create a message and, for user role, append an assistant reply via the LLM
    public function store(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'uuid', Rule::exists('chats', 'id')],
            'role' => ['required', 'string', Rule::in(['user', 'assistant', 'system', 'tool'])],
            'content' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        // 1) Save incoming message
        $msg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => $request->role === 'user' ? (int) (Auth::id() ?? 0) : null,
            'role' => $request->role,
            'content' => $request->input('content'),
            'metadata' => $request->metadata,
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

        if ($sp = config('llm.system_prompt')) {
            $messages[] = ['role' => 'system', 'content' => $sp];
        }
        foreach ($history as $m) {
            if ($m->content === null)
                continue;
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        // 3) Call local LLM (Ollama)
        $modelFromSettings = data_get($chat->settings, 'model'); // optional override per chat
        $assistantText = app(\App\Services\LlmClient::class)->chat($messages, $modelFromSettings);

        // 4) Save assistant reply
        $assistant = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $chat->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => $assistantText,
            'metadata' => ['model' => trim($modelFromSettings ?? (string) config('llm.model'))],
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
        ]);

        // Save user message
        $userMsg = Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => (int) (Auth::id() ?? 0),
            'role' => 'user',
            'content' => $request->input('content'),
            'metadata' => null,
        ]);

        $chat = Chat::findOrFail($request->chat_id);

        $this->assertOwns($chat);
        // Defer title generation until after assistant message is persisted (end of stream)

        $history = $chat->messages()->orderBy('created_at')->take(20)->get();
        $messages = [];
        if ($sp = config('llm.system_prompt')) {
            $messages[] = ['role' => 'system', 'content' => $sp];
        }
        foreach ($history as $m) {
            if ($m->content === null)
                continue;
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        $modelFromSettings = data_get($chat->settings, 'model');
        $base = rtrim((string) config('llm.base_url'), '/');
        $model = trim($modelFromSettings ?? (string) config('llm.model'));

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ];

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

        return response()->stream(function () use ($httpRes, $chat, $assistantId, $model) {
            // Continue processing even if client disconnects
            @ignore_user_abort(true);
            $body = $httpRes->toPsrResponse()->getBody();
            $buffer = '';
            $assistantText = '';
            $modelUsed = $model;
            $assistantCreated = false;
            $lastPersistLen = 0;

            $send = function (array $data) {
                echo 'data: ' . json_encode($data) . "\n\n";
                @ob_flush();
                @flush();
            };

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
                                'metadata' => ['model' => $modelUsed],
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
                    'metadata' => ['model' => $modelUsed],
                ]);
            } else {
                // No token streamed (edge case); still create an empty assistant message
                \App\Models\Message::create([
                    'id' => $assistantId,
                    'chat_id' => $chat->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $assistantText,
                    'metadata' => ['model' => $modelUsed],
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
