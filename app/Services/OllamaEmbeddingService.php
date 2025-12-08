<?php

// app/Services/OllamaEmbeddingService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaEmbeddingService
{
    public function embed(string|array $input, string $model = 'nomic-embed-text'): array
    {
        $payload = ['model' => $model, 'input' => $input];
        $url = rtrim(config('services.ollama.url', 'http://localhost:11434'), '/') . '/api/embed';
        $resp = Http::timeout(180)->post($url, $payload)->throw()->json();
        return $resp['embeddings']; // list<list<float>>
    }
}
