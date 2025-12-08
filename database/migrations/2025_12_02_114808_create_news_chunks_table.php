<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;


return new class extends Migration {
    public function up(): void
    {
        // Ensure pgvector extension
        DB::statement("CREATE EXTENSION IF NOT EXISTS vector");

        // Create chunks table using Schema builder
        Schema::create('news_chunks', function (Blueprint $table) {

            $table->id();
            $table->foreignId('news_id')
                ->constrained('news')
                ->onDelete('cascade');
                
            $table->integer('chunk_no');
            $table->text('content');
            $table->integer('token_count')->nullable();

            // placeholder for tsvector (must be raw SQL after)
            $table->text('model')->default('nomic-embed-text');

            $table->text('category')->nullable();
            $table->text('country')->nullable();
            $table->text('city')->nullable();
            $table->timestampTz('date_sent')->nullable();

            $table->unique(['news_id', 'chunk_no']);
            $table->index('news_id');
        });

        DB::statement("ALTER TABLE news_chunks ADD COLUMN embedding vector(768)");

        // Add tsvector column with combined fields
        DB::statement("
            ALTER TABLE news_chunks
            ADD COLUMN content_tsv tsvector
            GENERATED ALWAYS AS (
                setweight(
                    to_tsvector('simple', normalize_ar_app(coalesce(content, ''))),
                    'A'
                )
                ||
                setweight(
                    to_tsvector(
                        'simple',
                        normalize_ar_app(
                            coalesce(category, '') || ' ' ||
                            coalesce(country, '') || ' ' ||
                            coalesce(city, '')
                        )
                    ),
                    'B'
                )
            ) STORED
        ");


        // Add indexes
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_chunks_tsv_gin
            ON news_chunks USING GIN (content_tsv)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_chunks_embed_hnsw
            ON news_chunks USING hnsw (embedding vector_cosine_ops)
            WITH (m=16, ef_construction=200)
        ");
        // Option B) IVFFlat index (good speed/recall tradeoff; build AFTER data load)
        // DB::statement("
        //     CREATE INDEX news_embedding_ivfflat
        //     ON news USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
        // ");

        // Optional: analyze for better plans
        DB::statement("ANALYZE news_chunks;");
    }

    public function down(): void
    {
        Schema::dropIfExists('news_chunks');
    }
};