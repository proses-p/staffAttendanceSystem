<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advance_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('advance_month', 7);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(
                ['company_id', 'advance_month', 'status'],
                'sar_company_month_status_idx'
            );
            $table->index(
                ['user_id', 'advance_month', 'status'],
                'sar_user_month_status_idx'
            );
            $table->foreign('company_id', 'sar_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id', 'sar_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('decided_by', 'sar_decided_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('monthly_payroll_records', function (Blueprint $table): void {
            $table->decimal('salary_advance_deduction', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payroll_records', function (Blueprint $table): void {
            $table->dropColumn('salary_advance_deduction');
        });

        Schema::dropIfExists('salary_advance_requests');
    }
};
