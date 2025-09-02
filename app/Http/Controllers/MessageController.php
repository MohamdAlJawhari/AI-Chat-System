<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class MessageController extends Controller
{
    public function index(Chat $chat)
    {
        $messages = $chat->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'metadata', 'created_at']);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'uuid', Rule::exists('chats', 'id')],
            'role' => ['required', 'string', Rule::in(['user', 'assistant', 'system', 'tool'])],
            'content' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        // 1) Save incoming message
        $msg = \App\Models\Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $request->chat_id,
            'user_id' => $request->role === 'user' ? (int) \App\Models\User::query()->value('id') : null,
            'role' => $request->role,
            'content' => $request->input('content'),
            'metadata' => $request->metadata,
        ]);

        // If it's assistant/system/tool, don't call the model
        if ($request->role !== 'user') {
            return response()->json($msg, 201);
        }

        // 2) Build short history + system prompt
        $chat = \App\Models\Chat::findOrFail($request->chat_id);

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
        $assistant = \App\Models\Message::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'chat_id' => $chat->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => $assistantText,
            'metadata' => ['model' => $modelFromSettings ?? config('llm.model')],
        ]);

        return response()->json([
            'user_message' => $msg,
            'assistant_message' => $assistant,
        ], 201);
    }

}
