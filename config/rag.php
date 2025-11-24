<?php

return [
    // Default number of archive documents to fetch for RAG responses
    'doc_limit' => (int) env('RAG_DOC_LIMIT', 16),

    // How many chunks per document the hybrid function should consider internally
    'per_doc' => (int) env('RAG_PER_DOC', 3),

    // Hybrid scoring weights (semantic vs lexical)
    'alpha' => (float) env('RAG_ALPHA', 0.80),
    'beta' => (float) env('RAG_BETA', 0.20),

    // Vector search tuning
    'hnsw_ef_search' => (int) env('RAG_HNSW_EF_SEARCH', 160),

    // Cap the amount of article body text sent to the LLM
    'body_character_limit' => (int) env('RAG_BODY_CHAR_LIMIT', 900),

    // Instruction prepended to archive context
    'instruction' => env('RAG_INSTRUCTION', 'Use these archive excerpts to answer. Cite Source [n] to ground claims. If the archive lacks the answer, say so.'),
];
