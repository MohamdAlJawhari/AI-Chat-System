<?php
// database/migrations/2025_10_09_000000_setup_hybrid_search.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- 1) pgvector
        CREATE EXTENSION IF NOT EXISTS vector;

        -- 2) chunks table
        CREATE TABLE IF NOT EXISTS news_item_chunks (
          chunk_id       BIGSERIAL PRIMARY KEY,
          news_item_id   TEXT NOT NULL REFERENCES news_items(id) ON DELETE CASCADE,
          chunk_no       INT  NOT NULL,
          content        TEXT NOT NULL,
          token_count    INT,
          content_tsv    tsvector GENERATED ALWAYS AS (
            setweight(to_tsvector('simple', normalize_ar_app(coalesce(content,''))), 'A')
          ) STORED,
          embedding      vector(1024),
          model          TEXT NOT NULL DEFAULT 'bge-m3',
          language       TEXT,
          category       TEXT,
          country        TEXT,
          city           TEXT,
          date_sent      TIMESTAMPTZ,
          created_at     TIMESTAMPTZ DEFAULT now(),
          UNIQUE (news_item_id, chunk_no)
        );

        CREATE INDEX IF NOT EXISTS idx_chunks_news_item_id ON news_item_chunks (news_item_id);
        CREATE INDEX IF NOT EXISTS idx_chunks_tsv_gin       ON news_item_chunks USING GIN (content_tsv);
        CREATE INDEX IF NOT EXISTS idx_chunks_embed_hnsw    ON news_item_chunks USING hnsw (embedding vector_cosine_ops)
          WITH (m=16, ef_construction=200);

        -- 3) queue
        CREATE TABLE IF NOT EXISTS news_item_ingest_queue (
          news_item_id  TEXT PRIMARY KEY,
          enqueued_at   TIMESTAMPTZ DEFAULT now(),
          reason        TEXT,
          tries         INT DEFAULT 0
        );

        CREATE OR REPLACE FUNCTION enqueue_news_item() RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
          INSERT INTO news_item_ingest_queue(news_item_id, reason)
          VALUES (NEW.id, TG_OP)
          ON CONFLICT (news_item_id) DO UPDATE
            SET enqueued_at = now(), reason = EXCLUDED.reason, tries = 0;
          RETURN NEW;
        END $$;

        DROP TRIGGER IF EXISTS news_items_enqueue_ai ON news_items;
        CREATE TRIGGER news_items_enqueue_ai
        AFTER INSERT OR UPDATE OF title,introduction,body,language,category,country,city,date_sent
        ON news_items
        FOR EACH ROW EXECUTE FUNCTION enqueue_news_item();

        -- 4) hybrid v2 function (doc-lex + chunk-lex + semantic)
        CREATE OR REPLACE FUNCTION hybrid_search_news_v2(
          query_text TEXT, query_vec vector(1024), k INT DEFAULT 20,
          alpha REAL DEFAULT 0.60, beta REAL DEFAULT 0.40
        )
        RETURNS TABLE(
          news_item_id TEXT, chunk_id BIGINT, chunk_no INT, title TEXT, introduction TEXT,
          snippet TEXT, ts_rank_norm DOUBLE PRECISION, chunk_rank_norm DOUBLE PRECISION,
          cosine_sim DOUBLE PRECISION, hybrid DOUBLE PRECISION
        )
        LANGUAGE sql STABLE AS
        $$
        WITH q AS (
          SELECT websearch_to_tsquery('simple', normalize_ar_app(query_text)) AS tsq, query_vec AS qv
        ),
        sem AS (
          SELECT c.chunk_id, c.chunk_no, c.news_item_id, c.content,
                 1 - (c.embedding <-> (SELECT qv FROM q)) AS cosine_sim
          FROM news_item_chunks c
          ORDER BY c.embedding <-> (SELECT qv FROM q)
          LIMIT GREATEST(k*25, 800)
        ),
        chunk_lex AS (
          SELECT c.chunk_id, c.chunk_no, c.news_item_id, c.content,
                 ts_rank(c.content_tsv, (SELECT tsq FROM q)) AS chunk_rank
          FROM news_item_chunks c
          WHERE c.content_tsv @@ (SELECT tsq FROM q)
          ORDER BY chunk_rank DESC
          LIMIT GREATEST(k*25, 800)
        ),
        cand AS (
          SELECT s.chunk_id, s.chunk_no, s.news_item_id, s.content, s.cosine_sim, 0::float8 AS chunk_rank FROM sem s
          UNION ALL
          SELECT l.chunk_id, l.chunk_no, l.news_item_id, l.content, 0::float8, l.chunk_rank FROM chunk_lex l
        ),
        agg AS (
          SELECT chunk_id, max(cosine_sim) AS cosine_sim, max(chunk_rank) AS chunk_rank
          FROM cand GROUP BY chunk_id
        ),
        joined AS (
          SELECT a.chunk_id, c.chunk_no, c.news_item_id, c.content, a.cosine_sim, a.chunk_rank,
                 n.title, n.introduction,
                 COALESCE(d.lex_rank,0) AS doc_rank
          FROM agg a
          JOIN news_item_chunks c ON c.chunk_id = a.chunk_id
          JOIN news_items n ON n.id = c.news_item_id
          LEFT JOIN (
            SELECT n.id, ts_rank(n.tsv, (SELECT tsq FROM q), 1) AS lex_rank
            FROM news_items n
            WHERE n.tsv @@ (SELECT tsq FROM q)
            ORDER BY lex_rank DESC
            LIMIT GREATEST(k*25, 800)
          ) d ON d.id = c.news_item_id
        ),
        norm AS (
          SELECT *,
            CASE WHEN max(doc_rank)   OVER() > 0 THEN doc_rank   / max(doc_rank)   OVER() ELSE 0 END AS ts_rank_norm,
            CASE WHEN max(chunk_rank) OVER() > 0 THEN chunk_rank / max(chunk_rank) OVER() ELSE 0 END AS chunk_rank_norm
          FROM joined
        )
        SELECT
          news_item_id, chunk_id, chunk_no, title, introduction,
          ts_headline('simple', content, (SELECT tsq FROM q), 'MaxWords=35,MinWords=20') AS snippet,
          ts_rank_norm, chunk_rank_norm, cosine_sim,
          ((1-alpha)*((1-beta)*ts_rank_norm + beta*chunk_rank_norm) + alpha*cosine_sim) AS hybrid
        FROM norm
        ORDER BY hybrid DESC
        LIMIT k;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS news_items_enqueue_ai ON news_items;
        DROP FUNCTION IF EXISTS enqueue_news_item();
        DROP FUNCTION IF EXISTS hybrid_search_news_v2(TEXT, vector(1024), INT, REAL, REAL);
        DROP INDEX IF EXISTS idx_chunks_embed_hnsw;
        DROP INDEX IF EXISTS idx_chunks_tsv_gin;
        DROP INDEX IF EXISTS idx_chunks_news_item_id;
        DROP TABLE IF EXISTS news_item_ingest_queue;
        DROP TABLE IF EXISTS news_item_chunks;
        SQL);
    }
};
