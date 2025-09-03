<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthToken
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        $token = null;
        if (preg_match('/^Bearer\s+(.*)$/i', $header, $m)) {
            $token = trim($m[1]);
        } elseif ($request->has('api_token')) {
            $token = (string) $request->query('api_token');
        }

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::where('api_token', $token)->first();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ((bool) $user->is_blocked) {
            return response()->json(['message' => 'Account blocked'], 403);
        }

        Auth::setUser($user);
        return $next($request);
    }
}

