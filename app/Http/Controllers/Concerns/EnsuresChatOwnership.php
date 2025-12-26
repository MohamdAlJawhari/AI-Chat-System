<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Chat;
use Illuminate\Support\Facades\Auth;

trait EnsuresChatOwnership
{
    /**
     * Abort if the authenticated user does not own the chat.
     */
    private function assertOwns(Chat $chat): void
    {
        if ($chat->user_id !== (int) (Auth::id() ?? 0)) {
            abort(403);
        }
    }
}

