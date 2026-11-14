<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCompensationProfile extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'basic_salary',
        'overtime_hourly_rate',
        'before_time_bonus_per_day',
        'daily_work_bonus_per_report',
        'deduct_unexcused_absences',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'overtime_hourly_rate' => 'decimal:2',
            'before_time_bonus_per_day' => 'decimal:2',
            'daily_work_bonus_per_report' => 'decimal:2',
            'deduct_unexcused_absences' => 'boolean',
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
}
