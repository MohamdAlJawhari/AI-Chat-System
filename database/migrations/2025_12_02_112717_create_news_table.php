<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->unsignedBigInteger('ref')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();

            // Timestamps WITH time zone
            $table->timestampTz('date_sent')->nullable();
            $table->timestampTz('date_created')->nullable();
            $table->timestampTz('date_modified')->nullable();

            $table->text('language')->nullable();
            $table->boolean('is_breaking_news')->default(false);
            $table->boolean('is_milestone')->default(false);

            $table->text('category')->nullable();
            $table->text('country')->nullable();
            $table->text('city')->nullable();

            $table->text('notes')->nullable();
            $table->text('byline')->nullable();
            $table->text('title')->nullable();
            $table->text('dateline')->nullable();
            $table->text('signature_line')->nullable();
            $table->text('introduction')->nullable();
            $table->text('description')->nullable();
            $table->longText('body')->nullable();

            $table->longText('content')->nullable();

            $table->text('media_type_id')->nullable();
            $table->text('path')->nullable();

            // Optional helpful btree indexes (fast filters/sorts)
            $table->index('date_sent');
            $table->index('country');
            $table->index('city');
            $table->index('category');

        });

        // Postgres array columns (Laravel schema builder has no native text[] type)
        DB::statement("ALTER TABLE news ADD COLUMN tags text[] DEFAULT '{}'::text[]");
        DB::statement("ALTER TABLE news ADD COLUMN keywords text[] DEFAULT '{}'::text[]");
        DB::statement("ALTER TABLE news ADD COLUMN speakers text[] DEFAULT '{}'::text[]");

        DB::statement("CREATE EXTENSION IF NOT EXISTS unaccent");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION normalize_ar_app(text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            PARALLEL SAFE
            STRICT
            AS $$
            SELECT translate(
                    regexp_replace(          -- Arabic-Indic digits ٠..٩ → 0..9
                        regexp_replace(      -- Persian digits ۰..۹ → 0..9
                        regexp_replace(      -- remove tatweel/kashida
                            regexp_replace(  -- remove Arabic diacritics 064B..065F
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

        DB::unprepared(<<<'SQL'
        -- Function
        CREATE OR REPLACE FUNCTION sync_news_content()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
        NEW.content := concat_ws(
            E'\n\n',
            NEW.title,
            NEW.introduction,
            NEW.description,
            NEW.body
        );
        RETURN NEW;
        END;
        $$;
        
        -- Trigger
        CREATE TRIGGER news_sync_content_biu
        BEFORE INSERT OR UPDATE OF title, introduction, description, body
        ON news
        FOR EACH ROW
        EXECUTE FUNCTION sync_news_content();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS news_sync_content_biu ON news;');
        DB::statement("DROP FUNCTION IF EXISTS sync_news_content CASCADE");
        DB::statement("DROP FUNCTION IF EXISTS normalize_ar_app CASCADE");
        Schema::dropIfExists('news');

    }
};
