<?php

namespace App\Models;

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
    ];

    public function casts(): array {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime:H:i:s',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'distance' => 'decimal:2',
        ];
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
