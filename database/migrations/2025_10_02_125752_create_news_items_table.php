<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            // Primary key is a TEXT id (e.g., 'lebanon_war/1.txt')
            $table->string('id', 1024)->primary();

            $table->text('ref')->nullable();
            $table->integer('user_id')->nullable();

            // Timestamps WITHOUT time zone (your schema)
            $table->timestamp('date_sent')->nullable();
            $table->timestamp('date_created')->nullable();
            $table->timestamp('date_modified')->nullable();

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

            $table->text('media_type_id')->nullable();
            $table->text('path')->nullable();
            $table->text('source_file')->nullable();

            // Optional helpful btree indexes (fast filters/sorts)
            $table->index('language');
            $table->index('date_sent');
        });

        // Postgres array columns (Laravel schema builder has no native text[] type)
        DB::statement("ALTER TABLE news_items ADD COLUMN tags text[] DEFAULT '{}'::text[]");
        DB::statement("ALTER TABLE news_items ADD COLUMN keywords text[] DEFAULT '{}'::text[]");
        DB::statement("ALTER TABLE news_items ADD COLUMN speakers text[] DEFAULT '{}'::text[]");
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
