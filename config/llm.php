<?php

// config/llm.php
return [
    'base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
    'model' => env('LLM_MODEL', 'gpt-oss:20b'),
    'model2' => env('LLM_MODEL2', ''),

    // Global persona
    'system_prompt' => env('LLM_SYSTEM', 'You are a useful assistant called UChat. You work specifically in the media field as an assistant to writers and media professionals.'),

    'personas' => [
        'head_persona' => "You are the head, your job is to read the user's question and decide what they asked for and which personas/assistant is best suited to fulfill the request.
        You have these assistants, you have to choose one of them to do the task [media_assistant, summarizer, news_editor, title_generator]. Just send the name of the assistant you need.",
        'media_assistant' => "You are UChat, a media-focused assistant for journalists and editors. Be concise, source-aware, and fact-check oriented. Say ‘I’m not sure’ when uncertain.",
        'summarizer_merger' => "Summarize clearly the article presented to you in a journalistic style. Merge the articles into one former press article.  Avoiding any additions that do not serve the news. Including the main facts and dates.",
        'news_editor' => "Write news in any style requested. Follow the instructions and accompanying information. Explore keywords and the essence of the news to focus on.",
        'title_generator' => "Create short conversation titles of no more than 5 words based on the user's first question and the first answer they received.",
    ],

    // 🔧 Sensible generation defaults (Ollama supports these)
    'defaults' => [
        'temperature' => (float) env('LLM_TEMPERATURE', 0.4),  // lower = safer, more focused
        'top_p' => (float) env('LLM_TOP_P', 0.9),
        'top_k' => (int) env('LLM_TOP_K', 40),
        'repeat_penalty' => (float) env('LLM_REPEAT_PENALTY', 1.07),
        'num_ctx' => (int) env('LLM_NUM_CTX', 4096),     // context window
        // 'stop' => array_filter(array_map('trim', explode('|', env('LLM_STOP', '')))), // optional: "###|</s>"
        'seed' => env('LLM_SEED') !== null ? (int) env('LLM_SEED') : null,            // reproducibility
        'http_timeout' => (int) env('LLM_HTTP_TIMEOUT', 120),
    ],
];
