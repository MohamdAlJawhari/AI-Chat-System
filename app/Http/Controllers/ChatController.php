<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

/**
 * Handles CRUD for chats owned by the authenticated session user.
 */
class ChatController extends Controller
{
    /** Return the current authenticated user id or abort(401). */
    private function currentUserId(): int {
        $id = Auth::id();
        if (!$id) abort(401);
        return (int) $id;
    }

    /** List chats for current user (most recent first). */
    public function index(Request $request)
    {
        $userId = $this->currentUserId();

        $chats = Chat::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get(['id','title','settings','created_at']);

        return response()->json($chats);
    }

    /** Create a new chat with optional title/settings. */
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

    /** Partial update of a chat (title and/or settings). */
    public function update(Request $request, Chat $chat)
    {
        // authorize ownership (dev-mode simple check)
        if ($chat->user_id !== $this->currentUserId()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        if (array_key_exists('title', $data)) {
            $chat->title = $data['title'];
        }
        if (array_key_exists('settings', $data)) {
            // merge settings rather than replacing entirely when partial is sent
            $chat->settings = array_merge($chat->settings ?? [], $data['settings'] ?? []);
        }
        $chat->save();

        return response()->json($chat);
    }

    /** Delete a chat (and cascade messages). */
    public function destroy(Chat $chat)
    {
        if ($chat->user_id !== $this->currentUserId()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $chat->delete();
        return response()->json(['deleted' => true]);
    }
}
