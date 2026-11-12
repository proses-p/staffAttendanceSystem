<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class StaffNotification extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'body',
        'is_pushed',
    ];

    #[Override]
    public function casts()
    {
        return [
            'is_pushed' => 'boolean',
        ];
    }
}
