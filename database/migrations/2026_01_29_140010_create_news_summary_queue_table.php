<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('news_summary_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('news_id')->unique();
            $table->timestampTz('enqueued_at')->useCurrent();
            $table->text('reason')->nullable();
            $table->integer('tries')->default(0);

            $table->foreign('news_id')
                ->references('id')->on('news')
                ->onDelete('cascade');

            $table->index('enqueued_at');
        });

        DB::unprepared("
            CREATE OR REPLACE FUNCTION enqueue_news_summary()
            RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
              INSERT INTO news_summary_queue(news_id, reason)
              VALUES (NEW.id, TG_OP)
              ON CONFLICT (news_id) DO UPDATE
                SET enqueued_at = now(), reason = EXCLUDED.reason, tries = 0;
              RETURN NEW;
            END $$;
        ");

        DB::unprepared("
        CREATE TRIGGER news_enqueue_summary
        AFTER INSERT OR UPDATE OF title, introduction, description, body, language, category, country, city, date_sent
        ON news
        FOR EACH ROW EXECUTE FUNCTION enqueue_news_summary();
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS news_enqueue_summary ON news;");
        Schema::dropIfExists('news_summary_queue');
        DB::unprepared("DROP FUNCTION IF EXISTS enqueue_news_summary() CASCADE;");
    }
};
