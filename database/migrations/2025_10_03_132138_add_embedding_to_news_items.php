<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // 153 is just an example — set to your embedding size
        DB::statement("ALTER TABLE news_items ADD COLUMN embedding vector(1024);"); //For bge-m3, it should be 1024

        // Choose ONE metric you’ll use consistently:
        // L2 (Euclidean): vector_l2_ops
        // Cosine:         vector_cosine_ops
        // Inner product:  vector_ip_ops

        // Option A) HNSW index (great quality, good default)
        DB::statement("
            CREATE INDEX news_items_embedding_hnsw
            ON news_items USING hnsw (embedding vector_cosine_ops);
        ");

        // Option B) IVFFlat index (good speed/recall tradeoff; build AFTER data load)
        // DB::statement("
        //     CREATE INDEX news_items_embedding_ivfflat
        //     ON news_items USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
        // ");

        // Optional: analyze for better plans
        DB::statement("ANALYZE news_items;");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS news_items_embedding_hnsw;");
        DB::statement("DROP INDEX IF EXISTS news_items_embedding_ivfflat;");
        DB::statement("ALTER TABLE news_items DROP COLUMN IF EXISTS embedding;");
    }
};

