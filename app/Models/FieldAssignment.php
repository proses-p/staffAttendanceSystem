<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'task_type',
        'purpose_description',
        'clock_in_at',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_at',
        'clock_out_lat',
        'clock_out_lng',
        'last_lat',
        'last_lng',
        'last_location_at',
        'gps_accuracy',
        'device_uuid',
        'is_mock_location',
        'is_flagged',
        'flag_reasons',
        'risk_score',
        'reviewed_by',
        'reviewed_at',
        'client_uuid',
        'is_offline',
        'time_source',
        'client_device_time',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'last_location_at' => 'datetime',
            'client_device_time' => 'datetime',
            'reviewed_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_mock_location' => 'boolean',
            'is_flagged' => 'boolean',
            'is_offline' => 'boolean',
            'flag_reasons' => 'array',
            'risk_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ClientVisit::class, 'field_assignment_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(FieldExpense::class, 'field_assignment_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('clock_out_at');
    }
}
