<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    /**
     * Call Ollama chat endpoint and return assistant text.
     *
     * @param array $messages  e.g. [['role'=>'system','content'=>'...'], ['role'=>'user','content'=>'...']]
     * @param string|null $model Optional override for model name
     * @param array $options  Extra params for Ollama (temperature, etc.)
     */
    public function chat(array $messages, ?string $model = null, array $options = []): string
    {
        $base  = rtrim((string) config('llm.base_url'), '/');
        // Be forgiving about whitespace in env/config
        $model = trim($model ?? (string) config('llm.model'));

        $payload = array_merge([
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ], $options);

        $res = Http::timeout(120)->post("$base/api/chat", $payload);
        $json = $res->json();

        return data_get($json, 'message.content', '');
    }

    /** Query Ollama for available model tags. */
    public function listModels(): array
    {
        $base = rtrim((string) config('llm.base_url'), '/');
        $res = Http::timeout(10)->get("$base/api/tags");
        if (!$res->successful()) return [];
        $out = [];
        foreach ((array) data_get($res->json(), 'models', []) as $m) {
            $name = (string) data_get($m, 'name');
            if ($name !== '') $out[] = $name;
        }
        return $out;
    }
}
