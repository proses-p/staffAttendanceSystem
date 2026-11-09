<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDeviceChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'staff_device_id',
        'purpose',
        'operation',
        'nonce',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
