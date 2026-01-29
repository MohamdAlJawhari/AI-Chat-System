<?php

// config/llm.php
return [
    'base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
    'model' => env('LLM_MODEL', 'gpt-oss:20b'),
    'model2' => env('LLM_MODEL2', ''),

    // Default persona name
    'default_persona' => env('LLM_DEFAULT_PERSONA', 'assistant'),

    // Personas
    'personas' => [
        'allowed' => ['auto', 'assistant', 'author', 'reporter', 'summarizer'],

        // Simple, transparent routing rules for when the user picks "Auto"
        'router' => [
            // If nothing matches, fall back to this persona
            'fallback' => 'assistant',
            // Keyword contains-matching, evaluated top to bottom
            // 'rules' => [
            //     'summarizer' => [
            //         'summarize', 'summarise', 'summary', 'summaries', 'tl;dr', 'tldr',
            //         'short version', 'shorten', 'condense', 'brief', 'recap', 'digest',
            //     ],
            //     'reporter' => [
            //         'breaking', 'latest', 'update', 'updates', 'live', 'live blog', 'live coverage',
            //         'what happened', 'what happened today', 'what is happening', 'on the ground', 'dispatch',
            //     ],
            //     'author' => [
            //         'write an article', 'write article', 'create an article', 'draft article',
            //         'rewrite', 'opinion', 'feature', 'feature story', 'longform', 'story idea',
            //         'compose article', 'news article', 'write a story',
            //     ],
            // ], 
        ],

        'assistant' => [
            'system' => env(
                'LLM_SYSTEM',
                'You are UChat, a helpful media assistant. Be concise, accurate, and clear.'
            ),
            'overrides' => [
                'temperature' => 0.4,
                'top_p' => 0.9,
            ],
        ],

        'author' => [
            'system' => 'You are a professional news author. Write well-structured articles, strong transitions, no invented facts.',
            'overrides' => [
                'temperature' => 0.6,
                'top_p' => 0.9,
            ],
        ],

        'reporter' => [
            'system' => 'You are a field reporter. Write factual, brief, timestamp-friendly updates. No embellishment.',
            'overrides' => [
                'temperature' => 0.3,
                'top_p' => 0.85,
            ],
        ],

        'summarizer' => [
            'system' => 'You summarize and condense. Keep only key facts, names, dates. No new info.',
            'overrides' => [
                'temperature' => 0.2,
                'top_p' => 0.9,
            ],
        ],
    ],

    // Defaults used if no override exists
    'defaults' => [
        'temperature' => (float) env('LLM_TEMPERATURE', 0.4),
        'top_p' => (float) env('LLM_TOP_P', 0.9),
        'top_k' => (int) env('LLM_TOP_K', 40),
        'repeat_penalty' => (float) env('LLM_REPEAT_PENALTY', 1.07),
        'num_ctx' => (int) env('LLM_NUM_CTX', 4096),
        'num_predict' => (int) env('LLM_NUM_PREDICT', 1024),
        'http_timeout' => (int) env('LLM_HTTP_TIMEOUT', 120),
    ],
];
