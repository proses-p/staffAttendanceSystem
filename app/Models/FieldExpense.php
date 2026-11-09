<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldExpense extends Model
{
    public const CATEGORIES = [
        'transport_fuel',
        'meals_refreshments',
        'client_entertainment',
        'other_emergency',
    ];

    public const CURRENCIES = ['TZS'];

    public const STATUS_PENDING = 'pending';

    public const RECEIPT_DISK = 'field-expenses-private';

    protected $fillable = [
        'field_assignment_id',
        'user_id',
        'category',
        'amount',
        'currency',
        'description',
        'receipt_image_path',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'client_uuid',
        'is_offline',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'is_offline' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function fieldAssignment(): BelongsTo
    {
        return $this->belongsTo(FieldAssignment::class, 'field_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewAudits(): HasMany
    {
        return $this->hasMany(FieldExpenseReviewAudit::class, 'field_expense_id');
    }
}
