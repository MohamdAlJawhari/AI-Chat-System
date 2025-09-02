<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Make a user (bigint id)
        $u = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => Hash::make('password')]
        );

        // 2) Create a chat with a PHP UUID
        $chatId = (string) Str::uuid();

        $chat = Chat::create([
            'id' => $chatId,
            'user_id' => $u->id, // bigint
            'title' => 'Welcome chat',
            'settings' => ['model' => 'local-llm', 'temperature' => 0.7],
        ]);

        // 3) Create messages pointing to the SAME chat id
        Message::create([
            'id' => (string) Str::uuid(),
            'chat_id' => $chatId,
            'user_id' => $u->id,
            'role' => 'user',
            'content' => 'Hello!',
        ]);

        Message::create([
            'id' => (string) Str::uuid(),
            'chat_id' => $chatId,
            'user_id' => null, // assistant/system/tool has no user_id
            'role' => 'assistant',
            'content' => 'Hi! How can I help?',
            'metadata' => ['tokens' => ['prompt' => 3, 'completion' => 5]],
        ]);
    }
}
