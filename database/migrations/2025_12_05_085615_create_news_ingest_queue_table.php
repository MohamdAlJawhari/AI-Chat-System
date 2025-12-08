<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;


return new class extends Migration {
    public function up(): void
    {
        // Create ingest queue (Schema version)
        Schema::create('news_ingest_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('news_id')->unique();
                    
            //enqueued_at = when it was last (re)queued
            $table->timestampTz('enqueued_at')->useCurrent();
            //reason = TG_OP → 'INSERT' or 'UPDATE'
            $table->text('reason')->nullable();
            //tries = how many times the worker failed
            $table->integer('tries')->default(0);

            $table->foreign('news_id')
                ->references('id')->on('news')
                ->onDelete('cascade');

            $table->index('enqueued_at');
            });

        // Trigger function
        DB::unprepared("
            CREATE OR REPLACE FUNCTION enqueue_news()
            RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
              INSERT INTO news_ingest_queue(news_id, reason)
              VALUES (NEW.id, TG_OP)
              ON CONFLICT (news_id) DO UPDATE
                SET enqueued_at = now(), reason = EXCLUDED.reason, tries = 0;
              RETURN NEW;
            END $$;
        ");

        // Trigger
        DB::unprepared("
        CREATE TRIGGER news_enqueue_ai
        AFTER INSERT OR UPDATE OF title, introduction, description, body, language, category, country, city, date_sent
        ON news
        FOR EACH ROW EXECUTE FUNCTION enqueue_news();

        ");
    }
    public function down(): void
    {
        // Drop trigger first (so news stays usable)
        DB::unprepared("DROP TRIGGER IF EXISTS news_enqueue_ai ON news;");

        // Drop queue table
        Schema::dropIfExists('news_ingest_queue');

        // Drop trigger function
        DB::unprepared("DROP FUNCTION IF EXISTS enqueue_news() CASCADE;");
    }
};