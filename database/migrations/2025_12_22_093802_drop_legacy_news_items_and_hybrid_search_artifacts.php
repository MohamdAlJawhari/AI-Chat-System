<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop legacy triggers first (examples: adjust names if different)
        DROP TRIGGER IF EXISTS news_items_enqueue_ai ON news_items;

        -- Drop legacy functions (examples: adjust names if different)
        DROP FUNCTION IF EXISTS enqueue_news_item();

        -- Drop legacy tables
        DROP TABLE IF EXISTS news_item_ingest_queue;
        DROP TABLE IF EXISTS news_item_chunks;
        DROP TABLE IF EXISTS news_items;

        -- If you created extra hybrid helper functions, drop them too (adjust names)
        DROP FUNCTION IF EXISTS hybrid_docs(text, int);
        DROP FUNCTION IF EXISTS hybrid_docs(text, int, real, real);
        SQL);
    }

    public function down(): void
    {
        // Usually leave down() empty for cleanup migrations
        // because recreating legacy schema is not desired.
    }
};
