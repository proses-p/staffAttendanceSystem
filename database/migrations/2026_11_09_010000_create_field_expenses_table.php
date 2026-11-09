<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_assignment_id')
                ->constrained('field_assignments')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('category', 40);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('TZS');
            $table->text('description')->nullable();
            $table->string('receipt_image_path')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->boolean('is_offline')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('field_assignment_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_expenses');
    }
};
