<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAdminController extends Controller
{
    public function index()
    {
        $this->requireAdmin();
        $users = User::query()->orderBy('id')->get(['id','name','email','role','is_blocked','created_at']);
        return response()->json($users);
    }

    public function block(User $user)
    {
        $this->requireAdmin();
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Cannot block yourself'], 422);
        }
        $user->is_blocked = true;
        $user->save();
        return response()->json(['blocked' => true]);
    }

    public function unblock(User $user)
    {
        $this->requireAdmin();
        $user->is_blocked = false;
        $user->save();
        return response()->json(['blocked' => false]);
    }

    private function requireAdmin(): void
    {
        $u = Auth::user();
        if (!$u || $u->role !== 'admin') {
            abort(403, 'Admin only');
        }
    }
}

