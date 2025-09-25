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
    /* 
    Create a new user (admin-only).
    - Generates a temporary password
    - Optionally sends a reset link to the new user's email
    */
    public function store(Request $request)
    {
        $this->requireAdmin(); // Only admins can use this

        // Validate input data
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', 'in:user,admin'], // default = user
            'send_reset' => ['nullable', 'boolean'], // send reset email?
        ]);

        // Default role is "user"
        $role = $data['role'] ?? 'user';

        // Generate a random temporary password
        $tempPassword = Str::random(8);

        // Create the user record in the database
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'password' => Hash::make($tempPassword),
        ]);

        // Try sending a password reset email if requested
        $emailStatus = null;
        if (!empty($data['send_reset'])) {
            try {
                $emailStatus = Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                // In case mail server fails
                $emailStatus = 'mail_error: ' . $e->getMessage();
            }
        }

        // Return the created user and some extra info
        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'created_at']),
            'temporary_password' => $tempPassword, // show admin the temp password
            'reset_link_sent' => !empty($data['send_reset']),
            'mail_status' => $emailStatus,
        ], 201);
    }

    /*
    List all users (admin-only).
    */
    public function index()
    {
        $this->requireAdmin();
        $users = User::query()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'role', 'is_blocked', 'created_at']);
        return response()->json($users);
    }

    /**
     * Block a user (admin-only).
     * Prevents blocking yourself.
     */
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

    /**
     * Unblock a user (admin-only).
     */
    public function unblock(User $user)
    {
        $this->requireAdmin();
        $user->is_blocked = false;
        $user->save();

        return response()->json(['blocked' => false]);
    }

    /**
     * Promote a user to admin (admin-only).
     */
    public function makeAdmin(User $user)
    {
        $this->requireAdmin();
        $user->role = 'admin';
        $user->save();

        return response()->json(['role' => $user->role]);
    }

    /**
     * Demote an admin back to a normal user.
     * Prevents an admin from changing their own role.
     */
    public function makeUser(User $user)
    {
        $this->requireAdmin();

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Cannot change your own role'], 422);
        }

        $user->role = 'user';
        $user->save();

        return response()->json(['role' => $user->role]);
    }

    /**
     * Delete a user (admin-only).
     * Prevents deleting your own account.
     */
    public function destroy(User $user)
    {
        $this->requireAdmin();

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }

        $user->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Helper method to check if current user is admin.
     * Aborts with 403 if not.
     */
    private function requireAdmin(): void
    {
        $u = Auth::user();

        if (!$u || $u->role !== 'admin') {
            abort(403, 'Admin only');
        }
    }
}
