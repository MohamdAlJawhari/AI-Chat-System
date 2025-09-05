<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;

// Redirect home to dashboard (chat)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Chat app page (Breeze sessions). Keep simple 'auth' without 'verified'.
Route::get('/dashboard', function () {
    return view('chat');
})->middleware(['auth'])->name('dashboard');

// Session-protected APIs under /api
Route::middleware('auth')->prefix('api')->group(function(){
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

// Public models list
Route::get('/api/models', function () {
    $m1 = trim((string) config('llm.model'));
    $m2 = trim((string) config('llm.model2'));
    $out = [];
    if ($m1 !== '') $out[] = $m1;
    if ($m2 !== '' && $m2 !== $m1) $out[] = $m2;
    return response()->json($out);
});

// Breeze default profile routes remain
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
