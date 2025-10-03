<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure tsv column
        DB::statement("ALTER TABLE news_items ADD COLUMN IF NOT EXISTS tsv tsvector");

        // 2) Our normalize function (app-owned)
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION normalize_ar_app(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
STRICT
AS $$
  SELECT translate(
           regexp_replace(                                  -- Arabic-Indic digits ٠..٩ → 0..9
             regexp_replace(                                -- Persian digits ۰..۹ → 0..9
               regexp_replace(                              -- remove tatweel/kashida
                 regexp_replace(                            -- remove Arabic diacritics 064B..065F
                   unaccent($1),
                   '[\u064B-\u065F]', '', 'g'
                 ),
                 '\u0640', '', 'g'
               ),
               '[\u06F0-\u06F9]', '0123456789', 'g'
             ),
             '[\u0660-\u0669]', '0123456789', 'g'
           ),
           -- from 6 chars             to 6 chars
           'آأإٱىة',
           'ااااايه'
         );
$$;
SQL);

        // 3) App-owned trigger function (new name)
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION news_items_tsv_update_app()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  NEW.tsv :=
    setweight(
      to_tsvector('simple',
        normalize_ar_app(coalesce(NEW.title,'') || ' ' ||
                         coalesce(NEW.introduction,'') || ' ' ||
                         coalesce(NEW.body,''))
      ), 'A'
    )
    ||
    setweight(
      to_tsvector('simple',
        normalize_ar_app(
          coalesce(NEW.description,'') || ' ' ||
          array_to_string(coalesce(NEW.tags,'{}'::text[]),' ') || ' ' ||
          array_to_string(coalesce(NEW.keywords,'{}'::text[]),' ') || ' ' ||
          coalesce(NEW.notes,'')
        )
      ), 'B'
    )
    ||
    setweight(
      to_tsvector('simple',
        normalize_ar_app(
          coalesce(NEW.byline,'') || ' ' ||
          to_char(NEW.date_sent,     'YYYY-MM-DD HH24:MI:SS') || ' ' ||
          to_char(NEW.date_created,  'YYYY-MM-DD HH24:MI:SS') || ' ' ||
          to_char(NEW.date_modified, 'YYYY-MM-DD HH24:MI:SS') || ' ' ||
          coalesce(NEW.category,'') || ' ' ||
          coalesce(NEW.country,'')  || ' ' ||
          coalesce(NEW.city,'')     || ' ' ||
          coalesce(NEW.signature_line,'') || ' ' ||
          array_to_string(coalesce(NEW.speakers,'{}'::text[]),' ')
        )
      ), 'C'
    );

  RETURN NEW;
END;
$$;
SQL);

        // 4) Triggers: drop any old app trigger name; create our app trigger
        DB::unprepared("DROP TRIGGER IF EXISTS trg_news_items_tsv_app ON news_items");
        // (Optional) disable legacy trigger if you want to avoid double work:
        // DB::unprepared("DROP TRIGGER IF EXISTS trg_news_items_tsv ON news_items");

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_news_items_tsv_app
BEFORE INSERT OR UPDATE OF
  title, introduction, body,
  description, tags, keywords, notes,
  byline, date_sent, date_created, date_modified,
  category, country, city, signature_line, speakers
ON news_items
FOR EACH ROW
EXECUTE FUNCTION news_items_tsv_update_app();
SQL);

        // 5) One-time backfill (uses our app function)
        DB::unprepared(<<<'SQL'
UPDATE news_items
SET tsv =
    setweight(
      to_tsvector('simple',
        normalize_ar_app(coalesce(title,'') || ' ' ||
                         coalesce(introduction,'') || ' ' ||
                         coalesce(body,''))
      ), 'A'
    )
    ||
    setweight(
      to_tsvector('simple',
        normalize_ar_app(
          coalesce(description,'') || ' ' ||
          array_to_string(coalesce(tags,'{}'::text[]),' ') || ' ' ||
          array_to_string(coalesce(keywords,'{}'::text[]),' ') || ' ' ||
          coalesce(notes,'')
        )
      ), 'B'
    )
    ||
    setweight(
      to_tsvector('simple',
        normalize_ar_app(
          coalesce(byline,'') || ' ' ||
          to_char(date_sent,     'YYYY-MM-DD HH24:MI:SS') || ' ' ||
          to_char(date_created,  'YYYY-MM-DD HH24:MI:SS') || ' ' ||
          to_char(date_modified, 'YYYY-MM-DD HH24:MI:SS') || ' ' ||
          coalesce(category,'') || ' ' ||
          coalesce(country,'')  || ' ' ||
          coalesce(city,'')     || ' ' ||
          coalesce(signature_line,'') || ' ' ||
          array_to_string(coalesce(speakers,'{}'::text[]),' ')
        )
      ), 'C'
    )
WHERE true;
SQL);

        // 6) Index
        DB::unprepared("CREATE INDEX IF NOT EXISTS news_items_tsv_gin ON news_items USING GIN (tsv)");
    }

    public function down(): void
    {
        // Drop only our app trigger & function (don’t touch legacy objects)
        DB::unprepared("DROP TRIGGER IF EXISTS trg_news_items_tsv_app ON news_items");
        DB::unprepared("DROP FUNCTION IF EXISTS news_items_tsv_update_app()");
        // (Optionally keep normalize_ar_app; if you really want to remove it, uncomment:)
        // DB::unprepared("DROP FUNCTION IF EXISTS normalize_ar_app(text)");

        DB::unprepared("DROP INDEX IF EXISTS news_items_tsv_gin");
        DB::statement("ALTER TABLE news_items DROP COLUMN IF EXISTS tsv");
    }
};
