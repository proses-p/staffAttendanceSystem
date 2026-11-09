<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_expense_review_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_expense_id')
                ->constrained('field_expenses')
                ->restrictOnDelete();
            $table->string('previous_status', 16);
            $table->string('new_status', 16);
            $table->foreignId('reviewer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->text('admin_notes')->nullable();
            $table->string('action', 16);
            $table->timestamps();

            $table->index(['field_expense_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_expense_review_audits');
    }
};
