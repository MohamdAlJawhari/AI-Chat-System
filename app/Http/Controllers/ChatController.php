<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    // Dev-only: pretend first user is the logged-in user
    private function currentUserId(): int {
        return User::query()->value('id');
    }

    public function index(Request $request)
    {
        $userId = $this->currentUserId();

        $chats = Chat::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get(['id','title','created_at']);

        return response()->json($chats);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'settings' => 'array',
        ]);

        $chat = Chat::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->currentUserId(),
            'title' => $request->input('title'),
            'settings' => $request->input('settings', []),
        ]);

        return response()->json($chat, 201);
    }
}
