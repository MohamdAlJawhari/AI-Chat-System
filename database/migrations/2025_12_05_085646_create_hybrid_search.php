<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    DB::unprepared(<<<'SQL'
    -- hybrid search: chunk-lex + semantic
    CREATE OR REPLACE FUNCTION hybrid_search(
      query_text TEXT,
      query_vec  vector(768),
      k          INT   DEFAULT 20,
      alpha      REAL  DEFAULT 0.80  -- weight for semantic vs lexical
    )
    RETURNS TABLE(
      news_id         BIGINT,
      chunk_id        BIGINT,
      chunk_no        INT,
      title           TEXT,
      introduction    TEXT,
      snippet         TEXT,
      chunk_rank_norm DOUBLE PRECISION,
      cosine_sim      DOUBLE PRECISION,
      hybrid          DOUBLE PRECISION
    )
    LANGUAGE sql
    STABLE
    AS $$
    WITH q AS (
      SELECT
        websearch_to_tsquery('simple', normalize_ar_app(query_text)) AS tsq,
        query_vec AS qv
    ),

    -- semantic candidates (by embedding similarity)
    sem AS (
      SELECT
        c.id       AS chunk_id,
        c.chunk_no,
        c.news_id,
        c.content,
        1 - (c.embedding <-> (SELECT qv FROM q)) AS cosine_sim
      FROM news_chunks c
      WHERE c.embedding IS NOT NULL
      ORDER BY c.embedding <-> (SELECT qv FROM q)
      LIMIT CASE
              WHEN COALESCE(k, 0) <= 0 THEN NULL
              ELSE GREATEST(k*25, 800)
            END
    ),

    -- lexical candidates (by FTS on chunk content)
    chunk_lex AS (
      SELECT
        c.id       AS chunk_id,
        c.chunk_no,
        c.news_id,
        c.content,
        ts_rank(c.content_tsv, (SELECT tsq FROM q)) AS chunk_rank
      FROM news_chunks c
      WHERE c.content_tsv @@ (SELECT tsq FROM q)
      ORDER BY chunk_rank DESC
      LIMIT CASE
              WHEN COALESCE(k, 0) <= 0 THEN NULL
              ELSE GREATEST(k*25, 800)
            END
    ),

    -- union semantic + lexical scores
    cand AS (
      SELECT
        s.chunk_id, s.chunk_no, s.news_id, s.content,
        s.cosine_sim,
        0::float8 AS chunk_rank
      FROM sem s

      UNION ALL

      SELECT
        l.chunk_id, l.chunk_no, l.news_id, l.content,
        0::float8 AS cosine_sim,
        l.chunk_rank
      FROM chunk_lex l
    ),

    ag AS (
      SELECT
        chunk_id,
        max(cosine_sim) AS cosine_sim,
        max(chunk_rank) AS chunk_rank
      FROM cand
      GROUP BY chunk_id
    ),

    joined AS (
      SELECT
        a.chunk_id,
        c.chunk_no,
        c.news_id,
        c.content,
        a.cosine_sim,
        a.chunk_rank,
        n.title,
        n.introduction
      FROM ag a
      JOIN news_chunks c ON c.id = a.chunk_id
      JOIN news       n ON n.id = c.news_id
    ),

    norm AS (
      SELECT *,
        CASE
          WHEN max(chunk_rank) OVER() > 0
            THEN chunk_rank / max(chunk_rank) OVER()
          ELSE 0
        END AS chunk_rank_norm
      FROM joined
    )

    SELECT
      news_id,
      chunk_id,
      chunk_no,
      title,
      introduction,
      ts_headline('simple', content, (SELECT tsq FROM q),
                  'MaxWords=35,MinWords=20') AS snippet,
      chunk_rank_norm,
      cosine_sim,
      ((1 - alpha) * chunk_rank_norm + alpha * cosine_sim) AS hybrid
    FROM norm
    ORDER BY hybrid DESC
    LIMIT CASE
            WHEN COALESCE(k, 0) <= 0 THEN NULL
            ELSE k
          END;
    $$;
    SQL);
  }

  public function down(): void
  {
    DB::unprepared("DROP FUNCTION IF EXISTS hybrid_search(TEXT, vector, INTEGER, REAL);");
  }
};