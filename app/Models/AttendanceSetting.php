<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'expected_arrival_time',
        'expected_work_minutes',
        'grace_period_minutes',
        'working_days',
    ];

    protected $casts = [
        'expected_arrival_time' => 'datetime:H:i',
        'expected_work_minutes' => 'integer',
        'grace_period_minutes' => 'integer',
        'working_days' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
