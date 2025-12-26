<?php

namespace App\Http\Controllers\Messages;

use App\Http\Controllers\Concerns\EnsuresChatOwnership;
use App\Http\Controllers\Controller;
use App\Models\Chat;

class MessageIndexController extends Controller
{
    use EnsuresChatOwnership;

    /**
     * List messages for a chat (oldest first).
     */
    public function index(Chat $chat)
    {
        $this->assertOwns($chat);

        $messages = $chat->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'metadata', 'created_at']);

        return response()->json($messages);
    }
}

