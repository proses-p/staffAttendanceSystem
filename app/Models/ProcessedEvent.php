<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    protected $fillable = ['client_uuid', 'user_id', 'type'];
}
