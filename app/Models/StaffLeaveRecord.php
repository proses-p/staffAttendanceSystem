<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeaveRecord extends Model
{
    public const TYPE_GENERIC = 'Leave';

    public const TYPES = [self::TYPE_GENERIC];

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'status',
        'recorded_by',
        'leave_type',
        'reason',
        'decision_note',
        'request_source',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
