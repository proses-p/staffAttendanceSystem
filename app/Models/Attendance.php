<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'sign_in_location',
        'sign_out_location',
    ];

    public function casts(): array
    {
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCheckInAtAttribute(): ?Carbon
    {
        return $this->timestampFor('check_in_time');
    }

    public function getCheckOutAtAttribute(): ?Carbon
    {
        $checkOutAt = $this->timestampFor('check_out_time');
        $checkInAt = $this->check_in_at;

        if (! $checkOutAt || ! $checkInAt) {
            return $checkOutAt;
        }

        if ($checkOutAt->lt($checkInAt)) {
            $checkOutAt->addDay();
        }

        return $checkOutAt;
    }

    public function getWorkDurationAttribute(): ?string
    {
        $checkInAt = $this->check_in_at;
        $checkOutAt = $this->check_out_at;

        if (! $checkInAt || ! $checkOutAt) {
            return null;
        }

        $workMinutes = (int) $checkInAt->diffInMinutes($checkOutAt);

        return sprintf(
            '%02dh %02dm',
            intdiv($workMinutes, 60),
            $workMinutes % 60
        );
    }

    private function timestampFor(string $column): ?Carbon
    {
        $time = $this->getRawOriginal($column);
        $date = $this->getRawOriginal('attendance_date');

        if (! $time || ! $date) {
            return null;
        }

        return Carbon::parse(
            Carbon::parse($date)->toDateString().' '.$time,
            config('app.timezone')
        );
    }
}
