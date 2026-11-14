<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFinancialBudget extends Model
{
    protected $fillable = [
        'company_id',
        'budget_month',
        'allocated_budget',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return ['allocated_budget' => 'decimal:2'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
