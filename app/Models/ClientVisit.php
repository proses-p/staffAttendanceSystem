<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientVisit extends Model
{
    protected $fillable = [
        'field_assignment_id',
        'user_id',
        'client_name',
        'notes',
        'arrived_at',
        'arrived_lat',
        'arrived_lng',
        'arrived_accuracy',
        'departed_at',
        'departed_lat',
        'departed_lng',
        'is_flagged',
        'client_uuid',
        'is_offline',
        'time_source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_flagged' => 'boolean',
            'is_offline' => 'boolean',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FieldAssignment::class, 'field_assignment_id');
    }
}
