<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\StaffDeviceProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffDeviceController extends Controller
{
    public function fieldSyncChallenge(
        Request $request,
        StaffDeviceProofService $proofs
    ): JsonResponse {
        try {
            return response()->json(
                $proofs->issueFieldSyncChallenge($request->user()),
                201
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'DEVICE_NOT_APPROVED',
                'message' => $exception->getMessage(),
            ], 403);
        }
    }

    public function enrollmentChallenge(
        Request $request,
        StaffDeviceProofService $proofs
    ): JsonResponse {
        $challenge = $proofs->issueEnrollmentChallenge($request->user());

        return response()->json([
            'challenge_id' => $challenge->id,
            'nonce' => $challenge->nonce,
            'user_id' => $request->user()->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ], 201);
    }

    public function attendanceChallenge(
        Request $request,
        StaffDeviceProofService $proofs
    ): JsonResponse {
        $validated = $request->validate([
            'operation' => ['required', Rule::in(['check-in', 'check-out'])],
        ]);

        return response()->json(
            $proofs->issueAttendanceChallenge($request->user(), $validated['operation']),
            201
        );
    }

    public function enroll(Request $request, StaffDeviceProofService $proofs): JsonResponse
    {
        $validated = $request->validate([
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['required', 'in:android'],
            'public_key' => ['required', 'string', 'max:10000'],
            'challenge_id' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'max:512'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $proofs->verifyEnrollment(
            $user,
            (int) $validated['challenge_id'],
            $validated['signature'],
            $validated['public_key']
        );

        $device = DB::transaction(function () use ($validated, $user): StaffDevice {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (StaffDevice::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'device' => 'An active device already exists. Contact an administrator to replace it.',
                ]);
            }

            $existing = StaffDevice::query()
                ->where('user_id', $user->id)
                ->where('public_key', $validated['public_key'])
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                return $existing;
            }

            if (StaffDevice::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists()) {
                throw ValidationException::withMessages([
                    'device' => 'A device registration is already awaiting administrator approval.',
                ]);
            }

            $fingerprint = hash('sha256', $validated['public_key']);
            if (StaffDevice::query()->where('public_key_fingerprint', $fingerprint)->exists()) {
                throw ValidationException::withMessages([
                    'public_key' => 'This device key was already registered. Use a new device key for recovery.',
                ]);
            }

            return StaffDevice::create([
                'user_id' => $user->id,
                'device_name' => $validated['device_name'] ?? null,
                'platform' => $validated['platform'],
                'public_key' => $validated['public_key'],
                'public_key_fingerprint' => $fingerprint,
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'Device registered and waiting for administrator approval.',
            'device' => [
                'id' => $device->id,
                'status' => $device->status,
            ],
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $devices = StaffDevice::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get(['id', 'device_name', 'platform', 'status', 'approved_at', 'revoked_at']);
        $device = $devices->firstWhere('status', 'active') ?? $devices->first();

        return response()->json([
            'registered' => $device !== null,
            'status' => $device?->status ?? 'not_registered',
            'devices' => $devices,
        ]);
    }
}
