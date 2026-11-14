<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->text('content');
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->boolean('is_pinned')->default(false);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(
                ['company_id', 'is_pinned', 'created_at'],
                'ca_company_pinned_idx'
            );
            $table->foreign('company_id', 'ca_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by', 'ca_created_by_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(
                ['announcement_id', 'user_id'],
                'ar_announcement_user_uq'
            );
            $table->foreign('announcement_id', 'ar_announcement_fk')
                ->references('id')->on('company_announcements')->cascadeOnDelete();
            $table->foreign('user_id', 'ar_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('company_announcements');
    }
};
