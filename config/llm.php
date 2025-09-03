<?php

return [
    'base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
    'model' => env('LLM_MODEL', 'gpt-oss:20b '),
    'model2' => env('LLM_MODEL2', ''),
    'system_prompt' => env('LLM_SYSTEM', 'You are a helpful assistant.'),
];
