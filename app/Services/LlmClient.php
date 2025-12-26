<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    // Call Ollama chat endpoint and return assistant text.
    public function chat(array $messages, ?string $model = null, array $options = []): string
    {
        $base = rtrim((string) config('llm.base_url'), '/');
        $model = trim($model ?? (string) config('llm.model'));

        // Merge config defaults + runtime options
        $defaults = config('llm.defaults', []);
        $opts = array_merge($defaults, $options);

        // Pull out http_timeout so it doesn't go to Ollama payload
        $httpTimeout = (int) ($opts['http_timeout'] ?? 120);
        unset($opts['http_timeout']);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => $opts, // ✅ Ollama expects generation params under "options"
        ];

        $res = Http::timeout($httpTimeout)->post("$base/api/chat", $payload)->throw();
        $json = $res->json();

        return (string) data_get($json, 'message.content', '');
    }

    /** To see what models are available in the LLM server.
     * No need for this function right now. But I will keep it for future use.
    */
    public function listModels(): array
    {
        $base = rtrim((string) config('llm.base_url'), '/');
        $res = Http::timeout(10)->get("$base/api/tags");
        if (!$res->successful())
            return [];
        $out = [];
        foreach ((array) data_get($res->json(), 'models', []) as $m) {
            $name = (string) data_get($m, 'name');
            if ($name !== '')
                $out[] = $name;
        }
        return $out;
    }
}
