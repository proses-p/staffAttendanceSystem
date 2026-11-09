<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAssignment;
use App\Services\StaffDeviceProofService;
use App\Services\OfflineEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FieldOperationsController extends Controller
{
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
}
