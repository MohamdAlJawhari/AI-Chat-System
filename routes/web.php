<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\FilterOptionsController;
use App\Http\Controllers\Messages\MessageIndexController;
use App\Http\Controllers\Messages\MessageSendController;
use App\Http\Controllers\Messages\MessageStreamController;
use Illuminate\Support\Facades\Route;

// routes/web.php
use App\Http\Controllers\HybridSearchController;
Route::middleware('auth')->get('/search', [HybridSearchController::class, 'index'])->name('search');


// Redirect home to dashboard (chat)
Route::get('/', fn () => redirect()->route('dashboard'));

// Chat app page (Breeze sessions). Keep simple 'auth' without 'verified'.
Route::middleware('auth')->get('/dashboard', fn () => view('pages.chat.index'))
    ->name('dashboard');

// Session-protected APIs under /api
Route::prefix('api')->middleware('auth')->group(function () {

    // ---- Chats ----
    Route::name('chats.')->group(function () {
        Route::get('/chats',          [ChatController::class, 'index'])->name('index');
        Route::post('/chats',         [ChatController::class, 'store'])->name('store');
        Route::patch('/chats/{chat}', [ChatController::class, 'update'])->name('update');
        Route::delete('/chats/{chat}',[ChatController::class, 'destroy'])->name('destroy');
        Route::get('/chats/{chat}/messages', [MessageIndexController::class, 'index'])
            ->name('messages.index');
    });

    // ---- Messages ----
    Route::name('messages.')->group(function () {
        Route::post('/messages',        [MessageSendController::class, 'store'])->name('store');
        Route::post('/messages/stream', [MessageStreamController::class, 'stream'])->name('stream');
    });

    // Admin user management
    Route::get('/admin/users', [UserAdminController::class, 'index']);
    Route::post('/admin/users', [UserAdminController::class, 'store']);
    Route::post('/admin/users/{user}/block', [UserAdminController::class, 'block']);
    Route::post('/admin/users/{user}/unblock', [UserAdminController::class, 'unblock']);
    Route::post('/admin/users/{user}/make-admin', [UserAdminController::class, 'makeAdmin']);
    Route::post('/admin/users/{user}/make-user', [UserAdminController::class, 'makeUser']);
    Route::delete('/admin/users/{user}', [UserAdminController::class, 'destroy']);

    // Distinct filter options (cached)
    Route::get('/filter-options', FilterOptionsController::class)->name('filters.options');
});

// Public: list available LLM models
Route::get('/api/models', function () {
    $m1 = trim((string) config('llm.model'));
    $m2 = trim((string) config('llm.model2'));
    $out = [];
    if ($m1 !== '') $out[] = $m1;
    if ($m2 !== '' && $m2 !== $m1) $out[] = $m2;
    return response()->json($out);
})->name('models.index');

// Breeze profile routes (session auth)
Route::middleware('auth')->group(function () {
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',[ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Admin Control UI page
Route::middleware('auth')->get('/admin', function () {
    $u = auth()->user();
    abort_if(!$u || $u->role !== 'admin', 403, 'Admin only');
    return view('pages.admin.users');
})->name('admin.control');
