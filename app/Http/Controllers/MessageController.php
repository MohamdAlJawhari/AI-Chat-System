<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function index(Chat $chat)
    {
        $messages = $chat->messages()
            ->orderBy('created_at')
            ->get(['id','role','content','metadata','created_at']);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'chat_id'  => ['required','uuid', Rule::exists('chats','id')],
            'role'     => ['required','string', Rule::in(['user','assistant','system','tool'])],
            'content'  => ['nullable','string'],
            'metadata' => ['nullable','array'],
        ]);

        $msg = Message::create([
            'id'       => (string) Str::uuid(),
            'chat_id'  => $request->chat_id,
            // for now, user_id only for 'user' role (dev mode)
            'user_id'  => $request->role === 'user' ? \App\Models\User::query()->value('id') : null,
            'role'     => $request->role,
            'content'  => $request->input('content'),
            'metadata' => $request->metadata,
        ]);

        return response()->json($msg, 201);
    }
}
