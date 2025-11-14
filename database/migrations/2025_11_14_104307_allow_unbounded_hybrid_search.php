<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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
          LIMIT CASE WHEN COALESCE(k,0) <= 0 THEN NULL ELSE GREATEST(k*25, 800) END
        ),
        chunk_lex AS (
          SELECT c.chunk_id, c.chunk_no, c.news_item_id, c.content,
                 ts_rank(c.content_tsv, (SELECT tsq FROM q)) AS chunk_rank
          FROM news_item_chunks c
          WHERE c.content_tsv @@ (SELECT tsq FROM q)
          ORDER BY chunk_rank DESC
          LIMIT CASE WHEN COALESCE(k,0) <= 0 THEN NULL ELSE GREATEST(k*25, 800) END
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
            LIMIT CASE WHEN COALESCE(k,0) <= 0 THEN NULL ELSE GREATEST(k*25, 800) END
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
        LIMIT CASE WHEN COALESCE(k,0) <= 0 THEN NULL ELSE k END;
        $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION hybrid_search_news_docs(
          query_text TEXT,
          query_vec  vector(1024),
          k_docs     INT    DEFAULT 10,
          per_doc    INT    DEFAULT 3,
          alpha      REAL   DEFAULT 0.60,
          beta       REAL   DEFAULT 0.40
        )
        RETURNS TABLE(
          news_item_id  TEXT,
          doc_score     DOUBLE PRECISION,
          title         TEXT,
          introduction  TEXT,
          body          TEXT,
          best_snippet  TEXT
        )
        LANGUAGE sql STABLE AS
        $$
        WITH r AS (
          SELECT *
          FROM hybrid_search_news_v2(query_text, query_vec,
                 CASE WHEN COALESCE(k_docs,0) <= 0 THEN NULL ELSE GREATEST(k_docs*per_doc*8, 200) END,
                 alpha, beta)
        ),
        best AS (
          SELECT DISTINCT ON (news_item_id)
                 news_item_id, chunk_id, snippet, hybrid
          FROM r
          ORDER BY news_item_id, hybrid DESC
        ),
        doc_scores AS (
          SELECT news_item_id,
                 MAX(hybrid) AS max_h,
                 AVG(hybrid) AS avg_h
          FROM r GROUP BY news_item_id
        )
        SELECT n.id AS news_item_id,
               (ds.max_h + 0.2*ds.avg_h) AS doc_score,
               n.title, n.introduction, n.body,
               b.snippet AS best_snippet
        FROM doc_scores ds
        JOIN news_items n ON n.id = ds.news_item_id
        JOIN best b       ON b.news_item_id = ds.news_item_id
        ORDER BY doc_score DESC
        LIMIT CASE WHEN COALESCE(k_docs, 0) <= 0 THEN NULL ELSE k_docs END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
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

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION hybrid_search_news_docs(
          query_text TEXT,
          query_vec  vector(1024),
          k_docs     INT    DEFAULT 10,
          per_doc    INT    DEFAULT 3,
          alpha      REAL   DEFAULT 0.60,
          beta       REAL   DEFAULT 0.40
        )
        RETURNS TABLE(
          news_item_id  TEXT,
          doc_score     DOUBLE PRECISION,
          title         TEXT,
          introduction  TEXT,
          body          TEXT,
          best_snippet  TEXT
        )
        LANGUAGE sql STABLE AS
        $$
        WITH r AS (
          SELECT *
          FROM hybrid_search_news_v2(query_text, query_vec,
                 GREATEST(k_docs*per_doc*8, 200), alpha, beta)
        ),
        best AS (
          SELECT DISTINCT ON (news_item_id)
                 news_item_id, chunk_id, snippet, hybrid
          FROM r
          ORDER BY news_item_id, hybrid DESC
        ),
        doc_scores AS (
          SELECT news_item_id,
                 MAX(hybrid) AS max_h,
                 AVG(hybrid) AS avg_h
          FROM r GROUP BY news_item_id
        )
        SELECT n.id AS news_item_id,
               (ds.max_h + 0.2*ds.avg_h) AS doc_score,
               n.title, n.introduction, n.body,
               b.snippet AS best_snippet
        FROM doc_scores ds
        JOIN news_items n ON n.id = ds.news_item_id
        JOIN best b       ON b.news_item_id = ds.news_item_id
        ORDER BY doc_score DESC
        LIMIT CASE WHEN COALESCE(k_docs, 0) <= 0 THEN NULL ELSE k_docs END;
        $$;
        SQL);
    }
};
