<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAssignment;
use App\Models\FieldExpense;
use App\Services\StaffDeviceProofService;
use App\Services\OfflineEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class FieldOperationsController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $completed = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereNotNull('clock_out_at')
            ->whereBetween('clock_in_at', [$monthStart, $monthEnd])
            ->with('expenses:id,field_assignment_id,amount,description,receipt_image_path');
        $completedAssignments = $completed->get();
        $dailyMinutes = [];
        foreach ($completedAssignments as $assignment) {
            if (! $assignment->clock_in_at || ! $assignment->clock_out_at ||
                $assignment->clock_out_at->lte($assignment->clock_in_at)) {
                continue;
            }
            $date = $assignment->clock_in_at->toDateString();
            $minutes = min(
                14 * 60,
                (int) floor($assignment->clock_in_at->diffInSeconds($assignment->clock_out_at) / 60)
            );
            $dailyMinutes[$date] = min(14 * 60, ($dailyMinutes[$date] ?? 0) + $minutes);
        }
        $totalMinutes = array_sum($dailyMinutes);
        $active = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();
        $recent = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereNotNull('clock_out_at')
            ->with('expenses:id,field_assignment_id,amount,description,receipt_image_path')
            ->latest('clock_out_at')
            ->limit(10)
            ->get()
            ->map(fn (FieldAssignment $assignment): array => $this->visitData($assignment));

        return response()->json([
            'stats' => [
                'total_field_hours' => round($totalMinutes / 60, 2),
                'avg_daily_hours' => $dailyMinutes === []
                    ? 0.0
                    : round($totalMinutes / 60 / count($dailyMinutes), 2),
                'total_visits' => $completedAssignments->count(),
            ],
            'active_visit' => $active ? $this->visitData($active) : null,
            'recent_visits' => $recent,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_or_service_name' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $user = $request->user();

        $assignment = DB::transaction(function () use ($data, $user): FieldAssignment {
            $hasActive = FieldAssignment::query()
                ->where('user_id', $user->id)
                ->where('company_id', $user->company_id)
                ->whereNull('clock_out_at')
                ->lockForUpdate()
                ->exists();
            if ($hasActive) {
                throw ValidationException::withMessages([
                    'field_visit' => ['End your active field visit before starting another.'],
                ]);
            }

            return FieldAssignment::query()->create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'task_type' => 'client_meeting',
                'client_or_service_name' => $data['client_or_service_name'],
                'purpose_description' => $data['purpose'],
                'clock_in_at' => now(),
                'clock_in_lat' => $data['latitude'],
                'clock_in_lng' => $data['longitude'],
                'last_lat' => $data['latitude'],
                'last_lng' => $data['longitude'],
                'last_location_at' => now(),
                'time_source' => 'server',
            ]);
        });

        return response()->json(['active_visit' => $this->visitData($assignment)], 201);
    }

    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visit_id' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
            'expense_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'expense_description' => ['nullable', 'string', 'max:5000'],
            'receipt_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $user = $request->user();
        $assignmentQuery = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereNull('clock_out_at');
        if (! empty($data['visit_id'])) {
            $assignmentQuery->whereKey($data['visit_id']);
        }

        $storedPath = null;
        try {
            $assignment = DB::transaction(function () use (
                $assignmentQuery,
                $data,
                $request,
                &$storedPath,
                $user
            ): FieldAssignment {
                $assignment = (clone $assignmentQuery)->lockForUpdate()->firstOrFail();
                $photo = $data['receipt_photo'] ?? null;
                if ($photo instanceof UploadedFile) {
                    $storedPath = $photo->store('field_receipts/'.$user->id, 'public');
                    if (! $storedPath) {
                        throw new \RuntimeException('The receipt photo could not be stored.');
                    }
                }

                $assignment->update([
                    'clock_out_at' => now(),
                    'clock_out_lat' => $data['latitude'],
                    'clock_out_lng' => $data['longitude'],
                    'last_lat' => $data['latitude'],
                    'last_lng' => $data['longitude'],
                    'last_location_at' => now(),
                    'completion_notes' => $data['completion_notes'] ?? null,
                ]);

                $expenseAmount = (float) ($data['expense_amount'] ?? 0);
                $expenseDescription = $data['expense_description'] ?? null;
                if ($expenseAmount > 0 || $expenseDescription || $storedPath) {
                    FieldExpense::query()->create([
                        'field_assignment_id' => $assignment->id,
                        'user_id' => $user->id,
                        'category' => 'other_emergency',
                        'amount' => $expenseAmount,
                        'currency' => 'TZS',
                        'description' => $expenseDescription,
                        'receipt_image_path' => $storedPath,
                        'status' => FieldExpense::STATUS_PENDING,
                    ]);
                }

                return $assignment->fresh()->load('expenses:id,field_assignment_id,amount,description,receipt_image_path');
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Field visit completed.',
            'visit' => $this->visitData($assignment),
        ]);
    }

    public function sync(
        Request $request,
        OfflineEventProcessor $processor,
        StaffDeviceProofService $proofs
    ): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.client_uuid' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', 'in:field_clock_in,visit_arrive,visit_depart,field_clock_out'],
            'events.*.recorded_at' => ['required', 'date'],
            'events.*.device_time' => ['required', 'date'],
            'events.*.time_source' => ['required', 'in:trusted,device'],
            'events.*.payload' => ['required', 'array'],
            'events_json' => ['required', 'string', 'max:1000000'],
            'challenge_id' => ['required', 'integer', 'min:1'],
            'device_id' => ['required', 'integer', 'min:1'],
            'payload_hash' => ['required', 'string', 'size:64'],
            'signature' => ['required', 'string', 'max:512'],
        ]);
        if (json_decode($data['events_json'], true) !== $data['events']
            || ! hash_equals(hash('sha256', $data['events_json']), $data['payload_hash'])) {
            return response()->json([
                'code' => 'INVALID_FIELD_SYNC_PROOF',
                'message' => 'The field sync proof could not be verified.',
            ], 403);
        }
        try {
            $proofs->verifyFieldSync(
                $request->user(),
                (int) $data['challenge_id'],
                (int) $data['device_id'],
                $data['payload_hash'],
                $data['signature']
            );
        } catch (ValidationException) {
            return response()->json([
                'code' => 'INVALID_FIELD_SYNC_PROOF',
                'message' => 'The field sync proof could not be verified.',
            ], 403);
        }

        return response()->json([
            'results' => $processor->process($request->user(), $data['events'], $request),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $assignment = FieldAssignment::query()
            ->where('user_id', $request->user()->id)
            ->where('company_id', $request->user()->company_id)
            ->open()
            ->with(['visits' => fn ($query) => $query->orderBy('arrived_at')])
            ->latest('clock_in_at')
            ->first();

        if (! $assignment) {
            return response()->json(['assignment' => null]);
        }

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'client_uuid' => $assignment->client_uuid,
                'task_type' => $assignment->task_type,
                'client_or_service_name' => $assignment->client_or_service_name,
                'purpose_description' => $assignment->purpose_description,
                'clock_in_at' => $assignment->clock_in_at?->toIso8601String(),
                'visits' => $assignment->visits->map(fn ($visit): array => [
                    'client_uuid' => $visit->client_uuid,
                    'client_name' => $visit->client_name,
                    'notes' => $visit->notes,
                    'arrived_at' => $visit->arrived_at?->toIso8601String(),
                    'departed_at' => $visit->departed_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function time(): JsonResponse
    {
        return response()->json(['now' => now()->toIso8601String()]);
    }

    private function visitData(FieldAssignment $assignment): array
    {
        $expense = $assignment->expenses?->sortByDesc('id')->first();
        $receiptUrl = $expense?->receipt_image_path
            ? url(Storage::disk('public')->url($expense->receipt_image_path))
            : null;

        return [
            'id' => $assignment->id,
            'client_or_service_name' => $assignment->client_or_service_name
                ?? str_replace('_', ' ', $assignment->task_type),
            'purpose' => $assignment->purpose_description,
            'completion_notes' => $assignment->completion_notes,
            'clock_in_at' => $assignment->clock_in_at?->toIso8601String(),
            'clock_out_at' => $assignment->clock_out_at?->toIso8601String(),
            'duration_minutes' => $assignment->clock_in_at && $assignment->clock_out_at
                ? max(
                    0,
                    (int) floor(
                        $assignment->clock_in_at->diffInSeconds(
                            $assignment->clock_out_at
                        ) / 60
                    )
                )
                : null,
            'clock_in_lat' => $assignment->clock_in_lat,
            'clock_in_lng' => $assignment->clock_in_lng,
            'clock_out_lat' => $assignment->clock_out_lat,
            'clock_out_lng' => $assignment->clock_out_lng,
            'expense_amount' => $expense?->amount ?? 0,
            'expense_description' => $expense?->description,
            'receipt_photo_url' => $receiptUrl,
        ];
    }
}
