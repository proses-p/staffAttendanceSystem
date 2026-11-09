<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAssignment;
use App\Models\FieldExpense;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FieldExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $expenses = FieldExpense::query()
            ->where('user_id', $request->user()->id)
            ->with('fieldAssignment:id,task_type,clock_in_at,clock_out_at')
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => $expenses->getCollection()
                ->map(fn (FieldExpense $expense): array => $this->expenseData($expense))
                ->values(),
            'current_page' => $expenses->currentPage(),
            'last_page' => $expenses->lastPage(),
            'per_page' => $expenses->perPage(),
            'total' => $expenses->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field_assignment_id' => ['required', 'integer', 'min:1', 'exists:field_assignments,id'],
            'category' => ['required', 'string', Rule::in(FieldExpense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'currency' => ['nullable', 'string', Rule::in(FieldExpense::CURRENCIES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'receipt' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'client_uuid' => ['nullable', 'uuid'],
            'is_offline' => ['sometimes', 'boolean'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'admin_notes' => ['prohibited'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $receipt = $data['receipt'] ?? null;
        unset($data['receipt']);

        $storedPath = null;
        try {
            [$expense, $duplicate] = DB::transaction(function () use (
                $data,
                $receipt,
                $user,
                &$storedPath
            ): array {
                if (! empty($data['client_uuid'])) {
                    $existing = FieldExpense::query()
                        ->where('client_uuid', $data['client_uuid'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        abort_unless((int) $existing->user_id === (int) $user->id, 409);

                        return [$existing, true];
                    }
                }

                $assignment = FieldAssignment::query()
                    ->whereKey($data['field_assignment_id'])
                    ->lockForUpdate()
                    ->first();
                if (! $assignment) {
                    throw ValidationException::withMessages([
                        'field_assignment_id' => ['The selected field assignment is invalid.'],
                    ]);
                }
                abort_unless(
                    (int) $assignment->user_id === (int) $user->id
                    && (int) $assignment->company_id === (int) $user->company_id,
                    403
                );

                $isOffline = (bool) ($data['is_offline'] ?? false);
                $hasOfflineProof = $isOffline
                    && ! empty($data['client_uuid'])
                    && $assignment->is_offline;
                if ($assignment->clock_out_at && ! $hasOfflineProof) {
                    throw ValidationException::withMessages([
                        'field_assignment_id' => [
                            'Expenses can only be submitted against an open field assignment.',
                        ],
                    ]);
                }

                if ($receipt instanceof UploadedFile) {
                    $storedPath = $receipt->store('receipts', FieldExpense::RECEIPT_DISK);
                    if (! $storedPath) {
                        throw new \RuntimeException('The receipt could not be stored.');
                    }
                }

                $expense = FieldExpense::query()->create([
                    'field_assignment_id' => $assignment->id,
                    'user_id' => $user->id,
                    'category' => $data['category'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? 'TZS',
                    'description' => $data['description'] ?? null,
                    'receipt_image_path' => $storedPath,
                    'status' => FieldExpense::STATUS_PENDING,
                    'client_uuid' => $data['client_uuid'] ?? null,
                    'is_offline' => $isOffline,
                    'synced_at' => $isOffline ? now() : null,
                ]);

                return [$expense, false];
            });
        } catch (QueryException $exception) {
            if ($storedPath) {
                Storage::disk(FieldExpense::RECEIPT_DISK)->delete($storedPath);
            }

            if (! empty($data['client_uuid'])) {
                $existing = FieldExpense::query()
                    ->where('client_uuid', $data['client_uuid'])
                    ->first();
                if ($existing) {
                    abort_unless((int) $existing->user_id === (int) $user->id, 409);

                    return response()->json([
                        'expense' => $this->expenseData($existing),
                        'duplicate' => true,
                    ]);
                }
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk(FieldExpense::RECEIPT_DISK)->delete($storedPath);
            }

            throw $exception;
        }

        return response()->json([
            'expense' => $this->expenseData($expense),
            'duplicate' => $duplicate,
        ], $duplicate ? 200 : 201);
    }

    public function show(Request $request, FieldExpense $expense): JsonResponse
    {
        $this->authorizeView($request->user(), $expense);
        $expense->loadMissing('fieldAssignment:id,task_type,clock_in_at,clock_out_at');

        return response()->json(['expense' => $this->expenseData($expense)]);
    }

    public function receipt(Request $request, FieldExpense $expense): StreamedResponse
    {
        $this->authorizeView($request->user(), $expense);
        abort_unless($expense->receipt_image_path, 404);

        $disk = Storage::disk(FieldExpense::RECEIPT_DISK);
        abort_unless($disk->exists($expense->receipt_image_path), 404);

        return $disk->download($expense->receipt_image_path);
    }

    private function authorizeView(User $user, FieldExpense $expense): void
    {
        if ((int) $expense->user_id === (int) $user->id) {
            return;
        }

        $sameCompany = (int) $expense->fieldAssignment()->value('company_id')
            === (int) $user->company_id;
        abort_unless($sameCompany && Gate::forUser($user)->allows('admin-oversight'), 404);
    }

    private function expenseData(FieldExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'field_assignment_id' => $expense->field_assignment_id,
            'category' => $expense->category,
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'description' => $expense->description,
            'receipt_available' => (bool) $expense->receipt_image_path,
            'receipt_endpoint' => $expense->receipt_image_path
                ? "/api/v1/field/expenses/{$expense->id}/receipt"
                : null,
            'status' => $expense->status,
            'admin_notes' => $expense->admin_notes,
            'reviewed_at' => $expense->reviewed_at?->toIso8601String(),
            'is_offline' => $expense->is_offline,
            'synced_at' => $expense->synced_at?->toIso8601String(),
            'submitted_at' => $expense->created_at?->toIso8601String(),
            'field_assignment' => $expense->relationLoaded('fieldAssignment')
                ? [
                    'id' => $expense->fieldAssignment->id,
                    'task_type' => $expense->fieldAssignment->task_type,
                    'clock_in_at' => $expense->fieldAssignment->clock_in_at?->toIso8601String(),
                    'clock_out_at' => $expense->fieldAssignment->clock_out_at?->toIso8601String(),
                ]
                : null,
        ];
    }
}
