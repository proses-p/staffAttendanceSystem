<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyWorkSubmissionPhoto extends Model
{
    protected $fillable = [
        'daily_work_submission_id',
        'photo_path',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DailyWorkSubmission::class, 'daily_work_submission_id');
    }
}
