<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_financial_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('budget_month', 7);
            $table->decimal('allocated_budget', 15, 2);
            $table->string('currency', 3)->default('TZS');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'budget_month'], 'cfb_company_month_uq');
            $table->foreign('company_id', 'cfb_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('staff_compensation_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0);
            $table->decimal('before_time_bonus_per_day', 12, 2)->default(0);
            $table->decimal('daily_work_bonus_per_report', 12, 2)->default(0);
            $table->boolean('deduct_unexcused_absences')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'user_id'], 'scp_company_user_uq');
            $table->index('user_id', 'scp_user_idx');
            $table->foreign('company_id', 'scp_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id', 'scp_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('monthly_payroll_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('payroll_month', 7);
            $table->unsignedInteger('working_days_count')->default(0);
            $table->unsignedInteger('on_time_days')->default(0);
            $table->unsignedInteger('before_time_days')->default(0);
            $table->unsignedInteger('late_days')->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->decimal('on_time_rate', 5, 2)->default(0);
            $table->decimal('total_working_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->unsignedInteger('accepted_work_reports')->default(0);
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('overtime_pay', 15, 2)->default(0);
            $table->decimal('punctuality_allowance', 15, 2)->default(0);
            $table->decimal('work_report_allowance', 15, 2)->default(0);
            $table->decimal('absence_deduction', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            $table->unique(
                ['company_id', 'user_id', 'payroll_month'],
                'mpr_company_user_month_uq'
            );
            $table->index('user_id', 'mpr_user_idx');
            $table->foreign('company_id', 'mpr_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id', 'mpr_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approved_by', 'mpr_approved_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index('approved_by', 'mpr_approved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_payroll_records');
        Schema::dropIfExists('staff_compensation_profiles');
        Schema::dropIfExists('company_financial_budgets');
    }
};
