<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('news_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('news_id')->unique();
            $table->longText('summary')->nullable();
            $table->text('model')->nullable();
            $table->text('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestampTz('summarized_at')->nullable();
            $table->timestampsTz();

            $table->foreign('news_id')
                ->references('id')->on('news')
                ->onDelete('cascade');

            $table->index('summarized_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_summaries');
    }
};
