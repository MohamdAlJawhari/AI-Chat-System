<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;

Route::get('/chats', [ChatController::class, 'index']);
Route::post('/chats', [ChatController::class, 'store']);
Route::patch('/chats/{chat}', [ChatController::class, 'update']);
Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
Route::get('/chats/{chat}/messages', [MessageController::class, 'index']);
Route::post('/messages', [MessageController::class, 'store']);
Route::post('/messages/stream', [MessageController::class, 'storeStream']);

Route::get('/models', function () {
    // Only return models configured via env
    $m1 = trim((string) config('llm.model'));
    $m2 = trim((string) config('llm.model2'));
    $out = [];
    if ($m1 !== '') $out[] = $m1;
    if ($m2 !== '' && $m2 !== $m1) $out[] = $m2;
    return response()->json($out);
});
