<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    // Call Ollama chat endpoint and return assistant text.
    public function chat(array $messages, ?string $model = null, array $options = []): string
    {
        $base = rtrim((string) config('llm.base_url'), '/');
        // Be forgiving about whitespace in env/config
        $model = trim($model ?? (string) config('llm.model'));

        // Merge config defaults once, centrally
        $defaults = array_filter(config('llm.defaults', []), fn($v) => $v !== null && $v !== '');
        $opts = array_merge($defaults, $options);

        // Allow caller to pass a custom HTTP timeout via options
        $httpTimeout = 120;
        if (isset($options['http_timeout'])) {
            $httpTimeout = (int) $options['http_timeout'];
            unset($options['http_timeout']);
        }

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ], $options);

        $res = Http::timeout($httpTimeout)->post("$base/api/chat", $payload);
        $json = $res->json();

        return data_get($json, 'message.content', '');
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
