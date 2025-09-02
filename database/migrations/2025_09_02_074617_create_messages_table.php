<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('chat_id');

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('role'); // user | assistant | system | tool
            $table->text('content')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('chat_id')
                  ->references('id')->on('chats')
                  ->onDelete('cascade');

            $table->index(['chat_id', 'created_at']);
        });

        DB::statement("
            ALTER TABLE messages
            ADD CONSTRAINT messages_role_check
            CHECK (role IN ('user','assistant','system','tool'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
