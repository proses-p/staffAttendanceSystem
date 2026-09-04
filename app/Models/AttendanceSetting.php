<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'expected_arrival_time',
    ];
    protected $casts = [
        'expected_arrival_time' => 'datetime:H:i',
    ];
}
