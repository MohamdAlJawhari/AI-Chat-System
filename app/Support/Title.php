<?php

namespace App\Support;

use App\Models\Chat;
use App\Services\LlmClient;
use Illuminate\Support\Str;

class Title
{
    /**
     * Generate a concise chat title using the model and the first few messages
     * of the conversation (the "start"), instead of just the first sentence.
     *
     * Falls back to a heuristic from the first line when the model call fails.
     */
    public static function generateFromChatStart(Chat $chat, ?string $modelOverride = null): string
    {
        // Only use the very first user message and the first assistant reply
        $firstUser = $chat->messages()->where('role','user')->orderBy('created_at')->first();
        $firstAssistant = $chat->messages()->where('role','assistant')->orderBy('created_at')->first();

        $u = $firstUser ? trim((string) $firstUser->content) : '';
        $a = $firstAssistant ? trim((string) $firstAssistant->content) : '';
        // keep prompt compact to speed up the LLM call
        $u = Str::limit($u, 500, '…');
        $a = Str::limit($a, 700, '…');
        $conversationStart = trim("User: $u\nAssistant: $a");
        if ($conversationStart === '') {
            // Fallback to generic title
            return 'New chat';
        }

        try {
            /** @var LlmClient $llm */
            $llm = app(LlmClient::class);
            $prompt = <<<PROMPT
You are a headline creator. At the beginning of any conversation, create a short, descriptive headline (2 to 5 words) based on the language being used. No quotes or punctuation at the ends. Output only the title.

Chat Start:
{$conversationStart}
PROMPT;
            // Use requested model override or the primary LLM model
            $model = trim((string) ($modelOverride ?: config('llm.model')));
            $title = trim($llm->chat([
                ['role' => 'system', 'content' => 'You generate concise, descriptive chat titles.'],
                ['role' => 'user', 'content' => $prompt],
            ], $model, ['temperature' => 0.7, 'http_timeout' => 10]));

            // Sanitize: strip wrapping quotes and trailing punctuation, clamp length
            $title = trim($title, " \t\n\r\0\x0B\"'·-–—•:.");
            if ($title === '') throw new \RuntimeException('Empty title');
            return Str::limit($title, 60, '…');
        } catch (\Throwable $e) {
            // Heuristic fallback: use first line of the very first user message
            $firstUser = $chat->messages()->orderBy('created_at')->where('role','user')->first();
            $line = $firstUser ? (preg_split('/\r?\n/', trim((string) $firstUser->content))[0] ?? '') : '';
            $line = trim($line, " \t\-–—•:.");
            return Str::limit($line !== '' ? $line : 'New chat', 60, '…');
        }
    }
}
