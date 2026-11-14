<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'id'], 'ccm_company_id_idx');
        });

        Schema::create('private_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('quoted_message')->nullable();
            $table->text('message');
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->index(
                ['company_id', 'receiver_id', 'is_read'],
                'pcm_receiver_unread_idx'
            );
            $table->index(
                ['company_id', 'sender_id', 'receiver_id', 'id'],
                'pcm_conversation_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_chat_messages');
        Schema::dropIfExists('company_chat_messages');
    }
};
