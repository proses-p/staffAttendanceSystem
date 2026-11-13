<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffAssignedTask extends Model
{
    protected $fillable = [
        'company_id',
        'assigned_by',
        'user_id',
        'title',
        'instructions',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DailyWorkSubmission::class, 'assigned_task_id');
    }
}
