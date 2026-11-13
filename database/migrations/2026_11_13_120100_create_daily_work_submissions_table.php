<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_work_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('submission_date');
            $table->foreignId('assigned_task_id')
                ->nullable()
                ->constrained('staff_assigned_tasks')
                ->nullOnDelete();
            $table->string('completed_part');
            $table->text('description');
            $table->string('work_status', 16);
            $table->string('review_status', 24)->default('pending_review');
            $table->text('admin_feedback')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'user_id', 'submission_date'], 'dws_company_user_date_uq');
            $table->index(['company_id', 'review_status', 'submission_date'], 'dws_company_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_work_submissions');
    }
};
