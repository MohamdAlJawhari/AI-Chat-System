<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserAdminController extends Controller
{
    public function store(Request $request)
    {
        $this->requireAdmin();
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'role' => ['nullable','in:user,admin'],
            'send_reset' => ['nullable','boolean'],
        ]);
        $role = $data['role'] ?? 'user';
        $tempPassword = Str::random(12);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'password' => Hash::make($tempPassword),
        ]);

        $emailStatus = null;
        if (!empty($data['send_reset'])) {
            try {
                $emailStatus = Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                $emailStatus = 'mail_error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'user' => $user->only(['id','name','email','role','created_at']),
            'temporary_password' => $tempPassword,
            'reset_link_sent' => !empty($data['send_reset']),
            'mail_status' => $emailStatus,
        ], 201);
    }
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

    public function makeAdmin(User $user)
    {
        $this->requireAdmin();
        $user->role = 'admin';
        $user->save();
        return response()->json(['role' => $user->role]);
    }

    public function makeUser(User $user)
    {
        $this->requireAdmin();
        // Prevent an admin from changing their own role to avoid lockout via UI
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Cannot change your own role'], 422);
        }
        $user->role = 'user';
        $user->save();
        return response()->json(['role' => $user->role]);
    }

    public function destroy(User $user)
    {
        $this->requireAdmin();
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }
        $user->delete();
        return response()->json(['deleted' => true]);
    }

    private function requireAdmin(): void
    {
        $u = Auth::user();
        if (!$u || $u->role !== 'admin') {
            abort(403, 'Admin only');
        }
    }
}
