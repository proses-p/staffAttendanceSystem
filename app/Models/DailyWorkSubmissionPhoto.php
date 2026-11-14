<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DailyWorkSubmissionPhoto extends Model
{
    protected $fillable = [
        'daily_work_submission_id',
        'photo_path',
    ];

    public function getUrlAttribute(): string
    {
        $path = preg_replace(
            '#^(?:storage/)+#',
            '',
            ltrim($this->photo_path, '/')
        );

        return url(Storage::disk('public')->url($path));
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DailyWorkSubmission::class, 'daily_work_submission_id');
    }
}
