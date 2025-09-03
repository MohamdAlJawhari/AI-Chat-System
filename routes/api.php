<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\UserAdminController;

// Auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth.token');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth.token');

// Protected APIs (token auth)
Route::middleware('auth.token')->group(function () {
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::patch('/chats/{chat}', [ChatController::class, 'update']);
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
    Route::get('/chats/{chat}/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::post('/messages/stream', [MessageController::class, 'storeStream']);

    // Admin user management
    Route::get('/admin/users', [UserAdminController::class, 'index']);
    Route::post('/admin/users/{user}/block', [UserAdminController::class, 'block']);
    Route::post('/admin/users/{user}/unblock', [UserAdminController::class, 'unblock']);
});

Route::get('/models', function () {
    // Only return models configured via env
    $m1 = trim((string) config('llm.model'));
    $m2 = trim((string) config('llm.model2'));
    $out = [];
    if ($m1 !== '') $out[] = $m1;
    if ($m2 !== '' && $m2 !== $m1) $out[] = $m2;
    return response()->json($out);
});
