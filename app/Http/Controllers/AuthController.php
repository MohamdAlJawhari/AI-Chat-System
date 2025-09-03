<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        if (!$u) return response()->json(['message' => 'Unauthorized'], 401);
        return response()->json($u->only('id','name','email','role'));
    }
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
        ]);

        $user->api_token = Str::random(80);
        $user->save();

        return response()->json([
            'token' => $user->api_token,
            'user' => $user->only('id','name','email','role'),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ((bool) $user->is_blocked) {
            return response()->json(['message' => 'Account blocked'], 403);
        }

        $user->api_token = Str::random(80);
        $user->save();

        return response()->json([
            'token' => $user->api_token,
            'user' => $user->only('id','name','email','role'),
        ]);
    }

    public function logout(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            $user->api_token = null;
            $user->save();
        }
        return response()->json(['ok' => true]);
    }
}
