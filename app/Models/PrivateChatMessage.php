<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateChatMessage extends Model
{
    protected $fillable = [
        'company_id',
        'sender_id',
        'receiver_id',
        'quoted_message',
        'message',
        'edited_at',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
