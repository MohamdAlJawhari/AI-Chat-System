<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop old signatures so we can add filter + beta parameters
        DB::unprepared("DROP FUNCTION IF EXISTS hybrid_search_docs(TEXT, vector, INT, INT, REAL);");
        DB::unprepared("DROP FUNCTION IF EXISTS hybrid_search(TEXT, vector, INT, REAL);");

        DB::unprepared(<<<'SQL'
        -- hybrid search: chunk-lex + semantic with optional dataset filters
        CREATE OR REPLACE FUNCTION hybrid_search(
          query_text TEXT,
          query_vec  vector(768),
          k          INT   DEFAULT 20,
          alpha      REAL  DEFAULT 0.80,  -- weight for semantic vs lexical
          category_filter    TEXT DEFAULT NULL, -- new
          country_filter     TEXT DEFAULT NULL, -- new
          city_filter        TEXT DEFAULT NULL, -- new
          is_breaking_filter BOOLEAN DEFAULT NULL -- new
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
          JOIN news n ON n.id = c.news_id
          WHERE c.embedding IS NOT NULL
            AND (category_filter IS NULL OR category_filter = '' OR n.category ILIKE category_filter)
            AND (country_filter IS NULL OR country_filter = '' OR n.country ILIKE country_filter)
            AND (city_filter IS NULL OR city_filter = '' OR n.city ILIKE city_filter)
            AND (is_breaking_filter IS NULL OR n.is_breaking_news = is_breaking_filter)
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
          JOIN news n ON n.id = c.news_id
          WHERE c.content_tsv @@ (SELECT tsq FROM q)
            AND (category_filter IS NULL OR category_filter = '' OR n.category ILIKE category_filter)
            AND (country_filter IS NULL OR country_filter = '' OR n.country ILIKE country_filter)
            AND (city_filter IS NULL OR city_filter = '' OR n.city ILIKE city_filter)
            AND (is_breaking_filter IS NULL OR n.is_breaking_news = is_breaking_filter)
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
// ---------------------------------------------------------
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION hybrid_search_docs(
          query_text TEXT,
          query_vec  vector(768),
          k_docs     INT    DEFAULT 20,
          per_doc    INT    DEFAULT 3,
          alpha      REAL   DEFAULT 0.80,
          beta       REAL   DEFAULT 0.20,
          category_filter    TEXT DEFAULT NULL, -- new
          country_filter     TEXT DEFAULT NULL, -- new
          city_filter        TEXT DEFAULT NULL, -- new
          is_breaking_filter BOOLEAN DEFAULT NULL -- new
        )
        RETURNS TABLE(
          news_id       BIGINT,
          doc_score     DOUBLE PRECISION,
          title         TEXT,
          introduction  TEXT,
          body          TEXT,
          best_snippet  TEXT
        )
        LANGUAGE sql
        STABLE
        AS $$
        -- r = all relevant chunks using the chunk-level hybrid_search
        WITH r AS (
          SELECT *
          FROM hybrid_search(
            query_text,
            query_vec,
            CASE
              WHEN COALESCE(k_docs, 0) <= 0 THEN NULL
              ELSE GREATEST(k_docs*per_doc*8, 200)
            END,
            alpha,
            category_filter, -- new
            country_filter, -- new
            city_filter,   -- new
            is_breaking_filter  -- new
          )
        ),
        -- best = best chunk per news (highest hybrid score)
        best AS (
          SELECT DISTINCT ON (news_id)
                 news_id, chunk_id, snippet, hybrid
          FROM r
          ORDER BY news_id, hybrid DESC
        ),
        -- doc_scores = aggregate chunk scores to get a doc-level score
        doc_scores AS (
          SELECT
            news_id,
            MAX(hybrid) AS max_h,
            AVG(hybrid) AS avg_h
          FROM r
          GROUP BY news_id
        )
        SELECT
          n.id AS news_id,
          ((1 - beta) * ds.max_h + beta * ds.avg_h) AS doc_score,
          n.title,
          n.introduction,
          n.body,
          b.snippet AS best_snippet
        FROM doc_scores ds
        JOIN news n ON n.id = ds.news_id
        JOIN best b ON b.news_id = ds.news_id
        ORDER BY doc_score DESC
        LIMIT CASE
          WHEN COALESCE(k_docs, 0) <= 0 THEN NULL
          ELSE k_docs
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        // Revert to the previous function signatures (no filters / static beta)
        DB::unprepared("DROP FUNCTION IF EXISTS hybrid_search_docs(TEXT, vector, INT, INT, REAL, REAL, TEXT, TEXT, TEXT, BOOLEAN);");
        DB::unprepared("DROP FUNCTION IF EXISTS hybrid_search(TEXT, vector, INT, REAL, TEXT, TEXT, TEXT, BOOLEAN);");

        DB::unprepared(<<<'SQL'
        -- hybrid search: chunk-lex + semantic (original)
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

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION hybrid_search_docs(
          query_text TEXT,
          query_vec  vector(768),
          k_docs     INT    DEFAULT 20,
          per_doc    INT    DEFAULT 3,
          alpha      REAL   DEFAULT 0.80
        )
        RETURNS TABLE(
          news_id       BIGINT,
          doc_score     DOUBLE PRECISION,
          title         TEXT,
          introduction  TEXT,
          body          TEXT,
          best_snippet  TEXT
        )
        LANGUAGE sql
        STABLE
        AS $$
        -- r = all relevant chunks using the chunk-level hybrid_search
        WITH r AS (
          SELECT *
          FROM hybrid_search(
            query_text,
            query_vec,
            CASE
              WHEN COALESCE(k_docs, 0) <= 0 THEN NULL
              ELSE GREATEST(k_docs*per_doc*8, 200)
            END,
            alpha
          )
        ),
        -- best = best chunk per news (highest hybrid score)
        best AS (
          SELECT DISTINCT ON (news_id)
                 news_id, chunk_id, snippet, hybrid
          FROM r
          ORDER BY news_id, hybrid DESC
        ),
        -- doc_scores = aggregate chunk scores to get a doc-level score
        doc_scores AS (
          SELECT
            news_id,
            MAX(hybrid) AS max_h,
            AVG(hybrid) AS avg_h
          FROM r
          GROUP BY news_id
        )
        SELECT
          n.id AS news_id,
          (ds.max_h + 0.2*ds.avg_h) AS doc_score,
          n.title,
          n.introduction,
          n.body,
          b.snippet AS best_snippet
        FROM doc_scores ds
        JOIN news n ON n.id = ds.news_id
        JOIN best b ON b.news_id = ds.news_id
        ORDER BY doc_score DESC
        LIMIT CASE
          WHEN COALESCE(k_docs, 0) <= 0 THEN NULL
          ELSE k_docs
        END;
        $$;
        SQL);
    }
};
