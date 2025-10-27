<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
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

    public function down(): void
    {
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
        LIMIT k_docs;
        $$;
        SQL);
    }
};
