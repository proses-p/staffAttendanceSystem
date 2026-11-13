<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyWorkSubmission extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'submission_date',
        'assigned_task_id',
        'completed_part',
        'description',
        'work_status',
        'review_status',
        'admin_feedback',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'is_read' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTask(): BelongsTo
    {
        return $this->belongsTo(StaffAssignedTask::class, 'assigned_task_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DailyWorkSubmissionPhoto::class);
    }
}
