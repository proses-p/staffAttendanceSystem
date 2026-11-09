<?php

namespace App\Services;

use App\Models\FieldExpense;
use App\Models\FieldExpenseReviewAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FieldExpenseReviewService
{
    public function review(
        FieldExpense $expense,
        User $reviewer,
        string $decision,
        ?string $adminNotes = null
    ): FieldExpense {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('Unsupported expense review decision.');
        }

        return DB::transaction(function () use ($expense, $reviewer, $decision, $adminNotes): FieldExpense {
            $lockedExpense = FieldExpense::query()
                ->whereKey($expense->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $assignmentCompanyId = $lockedExpense->fieldAssignment()->value('company_id');
            abort_unless(
                $assignmentCompanyId !== null
                    && (int) $assignmentCompanyId === (int) $reviewer->company_id,
                404
            );

            if ((int) $lockedExpense->user_id === (int) $reviewer->id) {
                abort(403, 'You cannot review your own expense claim.');
            }

            if ($lockedExpense->status !== FieldExpense::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'expense' => ['Only pending expense claims can be reviewed.'],
                ]);
            }

            if ($decision === 'reject' && mb_strlen(trim((string) $adminNotes)) < 10) {
                throw ValidationException::withMessages([
                    'admin_notes' => ['Provide a meaningful rejection reason of at least 10 characters.'],
                ]);
            }

            $previousStatus = $lockedExpense->status;
            $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
            $reviewedAt = now();

            $lockedExpense->forceFill([
                'status' => $newStatus,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'admin_notes' => $decision === 'reject' ? trim((string) $adminNotes) : null,
            ])->save();

            FieldExpenseReviewAudit::query()->create([
                'field_expense_id' => $lockedExpense->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'admin_notes' => $decision === 'reject' ? trim((string) $adminNotes) : null,
                'action' => $decision,
            ]);

            return $lockedExpense->load([
                'user:id,name,employee_id,organization,company_id',
                'fieldAssignment',
                'reviewer:id,name',
            ]);
        }, attempts: 3);
    }
}
