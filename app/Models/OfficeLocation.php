<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeLocation extends Model
{
    protected $fillable = [
        'office_name',
        'latitude',
        'longitude',
        'allowed_radius',
    ];

    public function casts() {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'allowed_radius' => 'integer',
        ];
    }
}
