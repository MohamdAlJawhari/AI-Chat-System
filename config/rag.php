<?php

return [
    // Default number of archive documents to fetch for RAG responses
    'doc_limit' => (int) env('RAG_DOC_LIMIT', 15),

    // How many chunks per document the hybrid function should consider internally
    'per_doc' => (int) env('RAG_PER_DOC', 3),

    // Hybrid scoring weights (semantic vs lexical)
    'alpha' => (float) env('RAG_ALPHA', 0.80),
    'beta' => (float) env('RAG_BETA', 0.20),

    // Vector search tuning
    'hnsw_ef_search' => (int) env('RAG_HNSW_EF_SEARCH', 160),

    // Cap the amount of article body text sent to the LLM
    'body_character_limit' => (int) env('RAG_BODY_CHAR_LIMIT', 500),

    // Auto filter/weight router settings
    'auto_router' => [
        'model' => env('RAG_AUTO_ROUTER_MODEL', ''),
        'http_timeout' => (int) env('RAG_AUTO_ROUTER_TIMEOUT', 12),
        'max_values' => (int) env('RAG_AUTO_ROUTER_MAX_VALUES', 60),
        'max_values_country' => (int) env('RAG_AUTO_ROUTER_MAX_VALUES_COUNTRY', 0),
        'enabled_filters' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('RAG_AUTO_ROUTER_FILTERS', 'country'))
        ), fn($v) => $v !== '')),
    ],

    // Auto archive decision settings (used when archive mode is "auto")
    'auto_archive' => [
        'enabled' => (bool) env('RAG_AUTO_ARCHIVE', true),
        'model' => env('RAG_AUTO_ARCHIVE_MODEL', ''),
        'http_timeout' => (int) env('RAG_AUTO_ARCHIVE_TIMEOUT', 8),
    ],

    // Optional query rewrite for hybrid search
    'query_rewrite' => [
        'enabled' => (bool) env('RAG_QUERY_REWRITE', true),
        'model' => env('RAG_QUERY_REWRITE_MODEL', ''),
        'http_timeout' => (int) env('RAG_QUERY_REWRITE_TIMEOUT', 12),
        'max_chars' => (int) env('RAG_QUERY_REWRITE_MAX_CHARS', 220),
    ],

    // Instruction prepended to archive context
    'instruction' => env('RAG_INSTRUCTION', <<<TXT
    You are a press assistant. Use the sources inside <<ARCHIVE>> as primary evidence.
    Do NOT invent facts, numbers, dates, names, locations, or events not stated in the sources.
    Cite evidence for every factual claim taken from the archive.
    Always give your best full answer using your own knowledge. If the archive does not provide enough information, append this sentence at the end verbatim: 'المصادر في الأرشيف غير كافية للإجابة بدقة.' Never give the insufficiency sentence alone.
    You may add brief general background ONLY if you label it clearly as "معلومة عامة" and keep it to 2 sentences max.
    TXT),
    ];
