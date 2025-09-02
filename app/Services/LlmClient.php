<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    /**
     * @param array $messages  e.g. [['role'=>'system','content'=>'...'], ['role'=>'user','content'=>'...']]
     * @param string|null $model  override model name if needed
     * @param array $options  extra ollama params (temperature, etc.)
     */
    public function chat(array $messages, ?string $model = null, array $options = []): string
    {
        $base  = rtrim(config('llm.base_url'), '/');
        $model = $model ?? config('llm.model');

        $payload = array_merge([
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ], $options);

        $res = Http::timeout(120)->post("$base/api/chat", $payload);
        $json = $res->json();

        return data_get($json, 'message.content', '');
    }
}
