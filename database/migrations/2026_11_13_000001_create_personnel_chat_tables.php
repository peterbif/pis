<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personnel_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamp('admin_last_read_at')->nullable();
            $table->timestamp('personnel_last_read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('personnel_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_messages');
        Schema::dropIfExists('personnel_conversations');
    }
};
