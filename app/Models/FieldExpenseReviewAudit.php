<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldExpenseReviewAudit extends Model
{
    protected $fillable = [
        'field_expense_id',
        'previous_status',
        'new_status',
        'reviewer_id',
        'reviewed_at',
        'admin_notes',
        'action',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(FieldExpense::class, 'field_expense_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
