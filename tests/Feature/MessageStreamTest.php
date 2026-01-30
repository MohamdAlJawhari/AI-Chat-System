<?php

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Middleware\VerifyCsrfToken;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('stream endpoint persists user and assistant messages', function () {
    $user = User::factory()->create();
    $chat = Chat::create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'title' => null,
        'settings' => [],
    ]);

    $body = json_encode(['message' => ['content' => 'Hello ']]) . "\n"
        . json_encode(['message' => ['content' => 'world'], 'done' => true, 'model' => 'gpt-oss:20b']) . "\n";

    Http::fake([
        '*api/chat' => Http::response($body, 200, ['Content-Type' => 'application/json']),
    ]);

    $response = $this->actingAs($user)
        ->withoutMiddleware(VerifyCsrfToken::class)
        ->post('/api/messages/stream', [
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Hello world?',
        ]);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream');

    // Execute the stream callback to persist assistant message.
    $response->streamedContent();

    expect(Message::where('chat_id', $chat->id)->count())->toBe(2);

    $assistant = Message::where('chat_id', $chat->id)
        ->where('role', 'assistant')
        ->first();

    expect($assistant)->not()->toBeNull();
    expect($assistant->content)->toBe('Hello world');
});
