<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldExpense;
use App\Models\User;
use App\Services\FieldExpenseReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldExpenseReviewController extends Controller
{
    public function __construct(private readonly FieldExpenseReviewService $reviews) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'category' => ['nullable', Rule::in(FieldExpense::CATEGORIES)],
            'employee' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'field_assignment_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if (! $request->query->has('status')) {
            $filters['status'] = 'pending';
        }
        $companyId = $request->user()->company_id;
        $query = FieldExpense::query()
            ->whereHas('fieldAssignment', fn ($assignment) => $assignment
                ->where('company_id', $companyId))
            ->with([
                'user:id,name,employee_id',
                'fieldAssignment:id,task_type,clock_in_at,clock_out_at',
            ])
            ->when($filters['status'] ?? null, fn ($expenses, $status) => $expenses->where('status', $status))
            ->when($filters['category'] ?? null, fn ($expenses, $category) => $expenses->where('category', $category))
            ->when($filters['employee'] ?? null, fn ($expenses, $employee) => $expenses->where('user_id', $employee))
            ->when($filters['field_assignment_id'] ?? null, fn ($expenses, $assignment) => $expenses->where(
                'field_assignment_id',
                $assignment
            ))
            ->when($filters['date_from'] ?? null, fn ($expenses, $date) => $expenses->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($expenses, $date) => $expenses->whereDate('created_at', '<=', $date))
            ->latest('created_at');

        $expenses = $query->paginate(20)->withQueryString();
        $pendingCount = FieldExpense::query()
            ->where('status', 'pending')
            ->whereHas('fieldAssignment', fn ($assignment) => $assignment
                ->where('company_id', $companyId))
            ->count();
        $employees = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'staff')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('admin.field-expenses.index', compact(
            'expenses',
            'filters',
            'pendingCount',
            'employees'
        ));
    }

    public function show(Request $request, FieldExpense $expense): View
    {
        $this->ensureCompanyScope($request->user(), $expense);
        $expense->load([
            'user:id,name,employee_id,organization,company_id',
            'user.company:id,name',
            'fieldAssignment',
            'reviewer:id,name',
            'reviewAudits.reviewer:id,name',
        ]);
        $assignment = $expense->fieldAssignment;
        $elapsedSeconds = $assignment?->clock_in_at && $assignment?->clock_out_at
            ? max(0, $assignment->clock_in_at->diffInSeconds($assignment->clock_out_at, false))
            : null;

        return view('admin.field-expenses.show', compact('expense', 'assignment', 'elapsedSeconds'));
    }

    public function approve(Request $request, FieldExpense $expense): RedirectResponse
    {
        $request->validate([
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewer_id' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'admin_notes' => ['prohibited'],
        ]);
        $this->reviews->review($expense, $request->user(), 'approve');

        return redirect()->route('admin.field-expenses.show', $expense)
            ->with('status', 'Expense claim approved.');
    }

    public function reject(Request $request, FieldExpense $expense): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewer_id' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
        ]);
        $this->reviews->review($expense, $request->user(), 'reject', $data['admin_notes']);

        return redirect()->route('admin.field-expenses.show', $expense)
            ->with('status', 'Expense claim rejected with the recorded reason.');
    }

    public function receipt(Request $request, FieldExpense $expense): StreamedResponse
    {
        $this->ensureCompanyScope($request->user(), $expense);
        abort_unless($expense->receipt_image_path, 404);
        $disk = Storage::disk(FieldExpense::RECEIPT_DISK);
        abort_unless($disk->exists($expense->receipt_image_path), 404);

        return $disk->response($expense->receipt_image_path);
    }

    private function ensureCompanyScope(User $reviewer, FieldExpense $expense): void
    {
        $companyId = $expense->fieldAssignment()->value('company_id');
        abort_unless($companyId !== null && (int) $companyId === (int) $reviewer->company_id, 404);
    }
}
