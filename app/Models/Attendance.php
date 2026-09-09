<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_date',
        'check_in_time',
        'latitude',
        'longitude',
        'distance',
        'status',

        // check-out fields
        'check_out_time',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_distance',
    ];

    public function casts(): array {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'distance' => 'decimal:2',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_out_distance' => 'decimal:2',
        ];
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function getWorkDurationAttribute(): ?string
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return null;
        }

        $workMinutes = Carbon::parse($this->check_in_time)
            ->diffInMinutes(Carbon::parse($this->check_out_time));

        return sprintf(
            '%d hours %d minutes',
            intdiv($workMinutes, 60),
            $workMinutes % 60
        );
    }
}
