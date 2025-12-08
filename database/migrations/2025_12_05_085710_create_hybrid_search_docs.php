<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
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

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS hybrid_search_docs(
          TEXT,
          vector,
          INT,
          INT,
          REAL
        );
        SQL);
    }
};
