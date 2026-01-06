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

    // Instruction prepended to archive context
    'instruction' => env('RAG_INSTRUCTION', <<<TXT
    You are a press assistant. Use the sources inside <<ARCHIVE>> as primary evidence.
    Do NOT invent facts, numbers, dates, names, locations, or events not stated in the sources.
    Cite evidence like [1] or [2] for every factual claim taken from the archive.
    If the archive is insufficient, say: "المصادر في الأرشيف غير كافية للإجابة بدقة."
    You may add brief general background ONLY if you label it clearly as "معلومة عامة" and keep it to 2 sentences max.
    Write a crisp answer first, then optional bullet points if requested.
    TXT),
    ];