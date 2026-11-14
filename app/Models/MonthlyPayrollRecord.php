<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyPayrollRecord extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'payroll_month',
        'working_days_count',
        'on_time_days',
        'before_time_days',
        'late_days',
        'absent_days',
        'on_time_rate',
        'total_working_hours',
        'overtime_hours',
        'accepted_work_reports',
        'basic_salary',
        'overtime_pay',
        'punctuality_allowance',
        'work_report_allowance',
        'absence_deduction',
        'salary_advance_deduction',
        'net_pay',
        'risk_score',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'on_time_rate' => 'decimal:2',
            'total_working_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'basic_salary' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'punctuality_allowance' => 'decimal:2',
            'work_report_allowance' => 'decimal:2',
            'absence_deduction' => 'decimal:2',
            'salary_advance_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
