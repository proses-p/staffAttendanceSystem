<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldExpense;
use App\Models\User;
use App\Services\FieldExpenseReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFieldExpenseController extends Controller
{
    public function __construct(private readonly FieldExpenseReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'category' => ['sometimes', Rule::in(FieldExpense::CATEGORIES)],
            'employee' => ['sometimes', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'field_assignment_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $companyId = $request->user()->company_id;

        $expenses = FieldExpense::query()
            ->whereHas('fieldAssignment', fn ($query) => $query->where('company_id', $companyId))
            ->with([
                'user:id,name,employee_id,organization,company_id',
                'fieldAssignment:id,company_id,task_type,purpose_description,clock_in_at,clock_out_at',
            ])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->when(isset($filters['employee']), fn ($query) => $query->where('user_id', $filters['employee']))
            ->when(isset($filters['field_assignment_id']), fn ($query) => $query->where(
                'field_assignment_id',
                $filters['field_assignment_id']
            ))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate(
                'created_at',
                '>=',
                $filters['date_from']
            ))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate(
                'created_at',
                '<=',
                $filters['date_to']
            ))
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return response()->json([
            'data' => $expenses->getCollection()
                ->map(fn (FieldExpense $expense): array => $this->summary($expense))
                ->values(),
            'current_page' => $expenses->currentPage(),
            'last_page' => $expenses->lastPage(),
            'per_page' => $expenses->perPage(),
            'total' => $expenses->total(),
        ]);
    }

    public function show(Request $request, FieldExpense $expense): JsonResponse
    {
        $this->ensureCompanyScope($request->user(), $expense);
        $expense->load([
            'user:id,name,employee_id,organization,company_id',
            'user.company:id,name',
            'fieldAssignment',
            'reviewer:id,name',
            'reviewAudits.reviewer:id,name',
        ]);

        return response()->json(['expense' => $this->details($expense)]);
    }

    public function approve(Request $request, FieldExpense $expense): JsonResponse
    {
        $request->validate([
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewer_id' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'admin_notes' => ['prohibited'],
        ]);

        $reviewed = $this->reviews->review($expense, $request->user(), 'approve');

        return response()->json(['expense' => $this->details($reviewed)]);
    }

    public function reject(Request $request, FieldExpense $expense): JsonResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewer_id' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
        ]);

        if (mb_strlen(trim($data['admin_notes'])) < 10) {
            throw ValidationException::withMessages([
                'admin_notes' => ['Provide a meaningful rejection reason of at least 10 characters.'],
            ]);
        }

        $reviewed = $this->reviews->review(
            $expense,
            $request->user(),
            'reject',
            $data['admin_notes']
        );

        return response()->json(['expense' => $this->details($reviewed)]);
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

    private function summary(FieldExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'field_assignment_id' => $expense->field_assignment_id,
            'employee' => $this->employeeData($expense),
            'category' => $expense->category,
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'description' => $expense->description,
            'receipt_available' => (bool) $expense->receipt_image_path,
            'submitted_at' => $expense->created_at?->toIso8601String(),
            'status' => $expense->status,
            'field_assignment' => [
                'id' => $expense->fieldAssignment?->id,
                'task_type' => $expense->fieldAssignment?->task_type,
                'purpose_description' => $expense->fieldAssignment?->purpose_description,
                'clock_in_at' => $expense->fieldAssignment?->clock_in_at?->toIso8601String(),
                'clock_out_at' => $expense->fieldAssignment?->clock_out_at?->toIso8601String(),
            ],
        ];
    }

    private function details(FieldExpense $expense): array
    {
        $expense->loadMissing('reviewAudits.reviewer:id,name');
        $assignment = $expense->fieldAssignment;
        $clockIn = $assignment?->clock_in_at;
        $clockOut = $assignment?->clock_out_at;

        return [
            ...$this->summary($expense),
            'description' => $expense->description,
            'receipt_endpoint' => $expense->receipt_image_path
                ? "/api/v1/admin/field-expenses/{$expense->id}/receipt"
                : null,
            'employee' => [
                ...$this->employeeData($expense),
                'company' => $expense->user?->company?->name,
            ],
            'review' => [
                'reviewed_by' => $expense->reviewer?->name,
                'reviewed_at' => $expense->reviewed_at?->toIso8601String(),
                'admin_notes' => $expense->admin_notes,
            ],
            'field_assignment' => [
                'id' => $assignment?->id,
                'task_type' => $assignment?->task_type,
                'purpose_description' => $assignment?->purpose_description,
                'clock_in_at' => $clockIn?->toIso8601String(),
                'clock_out_at' => $clockOut?->toIso8601String(),
                'total_elapsed_seconds' => $clockIn && $clockOut
                    ? max(0, $clockIn->diffInSeconds($clockOut, false))
                    : null,
                'clock_in_location' => $this->point(
                    $assignment?->clock_in_lat,
                    $assignment?->clock_in_lng
                ),
                'clock_out_location' => $this->point(
                    $assignment?->clock_out_lat,
                    $assignment?->clock_out_lng
                ),
                'last_known_location' => $this->point(
                    $assignment?->last_lat,
                    $assignment?->last_lng
                ),
                'last_location_at' => $assignment?->last_location_at?->toIso8601String(),
                'gps_accuracy' => $assignment?->gps_accuracy,
                'is_mock_location' => (bool) $assignment?->is_mock_location,
                'is_flagged' => (bool) $assignment?->is_flagged,
                'flag_reasons' => $assignment?->flag_reasons ?? [],
                'risk_score' => $assignment?->risk_score,
            ],
            'review_history' => $expense->reviewAudits
                ->sortBy('reviewed_at')
                ->values()
                ->map(fn ($audit): array => [
                    'previous_status' => $audit->previous_status,
                    'new_status' => $audit->new_status,
                    'reviewer' => $audit->reviewer?->name,
                    'reviewed_at' => $audit->reviewed_at?->toIso8601String(),
                    'admin_notes' => $audit->admin_notes,
                    'action' => $audit->action,
                ])
                ->all(),
        ];
    }

    private function employeeData(FieldExpense $expense): array
    {
        return [
            'id' => $expense->user?->id,
            'name' => $expense->user?->name,
            'employee_id' => $expense->user?->employee_id,
            'organization' => $expense->user?->organization,
        ];
    }

    private function point(mixed $latitude, mixed $longitude): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
    }
}
