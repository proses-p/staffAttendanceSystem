<?php

namespace App\Services;

use App\Models\StaffDevice;
use App\Models\StaffDeviceChallenge;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSSLAsymmetricKey;

class StaffDeviceProofService
{
    public function issueFieldSyncChallenge(User $user): array
    {
        $device = StaffDevice::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'device' => 'An approved, active device is required to sync field activity.',
            ]);
        }

        $challenge = $this->createChallenge($user, $device, 'field_sync', null);

        return [
            'challenge_id' => $challenge->id,
            'nonce' => $challenge->nonce,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ];
    }

    public function verifyFieldSync(
        User $user,
        int $challengeId,
        int $deviceId,
        string $payloadHash,
        string $signature
    ): void {
        $device = StaffDevice::query()
            ->whereKey($deviceId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (! $device || ! preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
            throw ValidationException::withMessages([
                'signature' => 'The field sync proof is invalid.',
            ]);
        }

        $this->consumeAndVerify(
            $user,
            $challengeId,
            'field_sync',
            null,
            $signature,
            $device->public_key,
            fn (StaffDeviceChallenge $challenge): string => implode("\n", [
                'field-sync-v1',
                (string) $challenge->id,
                $challenge->nonce,
                (string) $user->id,
                (string) $device->id,
                $payloadHash,
            ]),
            $device
        );
    }

    public function issueEnrollmentChallenge(User $user): StaffDeviceChallenge
    {
        return DB::transaction(function () use ($user): StaffDeviceChallenge {
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

            if (StaffDevice::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists()) {
                throw ValidationException::withMessages([
                    'device' => 'A device registration is already awaiting administrator approval.',
                ]);
            }

            StaffDeviceChallenge::query()
                ->where('user_id', $user->id)
                ->where('purpose', 'enrollment')
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return $this->createChallenge($user, null, 'enrollment', null);
        });
    }

    public function issueAttendanceChallenge(User $user, string $operation): array
    {
        $device = StaffDevice::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'device' => 'An approved, active device is required for attendance.',
            ]);
        }

        $challenge = $this->createChallenge($user, $device, 'attendance', $operation);

        return [
            'challenge_id' => $challenge->id,
            'nonce' => $challenge->nonce,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ];
    }

    public function verifyEnrollment(
        User $user,
        int $challengeId,
        string $signature,
        string $publicKey
    ): void {
        $this->consumeAndVerify(
            $user,
            $challengeId,
            'enrollment',
            null,
            $signature,
            $publicKey,
            fn (StaffDeviceChallenge $challenge): string => implode("\n", [
                'staff-device-enroll-v1',
                (string) $challenge->id,
                $challenge->nonce,
                (string) $user->id,
            ])
        );
    }

    public function verifyAttendance(
        User $user,
        array $data,
        string $expectedOperation
    ): StaffDevice {
        $challenge = StaffDeviceChallenge::query()
            ->whereKey($data['challenge_id'])
            ->where('user_id', $user->id)
            ->where('staff_device_id', $data['device_id'])
            ->where('purpose', 'attendance')
            ->where('operation', $expectedOperation)
            ->first();

        $device = StaffDevice::query()
            ->whereKey($data['device_id'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (! $challenge || ! $device) {
            throw ValidationException::withMessages([
                'signature' => 'The attendance challenge or approved device is invalid.',
            ]);
        }

        $capturedAt = $this->parseLocationTimestamp($data['location_timestamp']);
        $now = now();
        if ($capturedAt->getTimestamp() < $now->copy()->subSeconds(90)->getTimestamp()
            || $capturedAt->getTimestamp() > $now->copy()->addSeconds(30)->getTimestamp()) {
            throw ValidationException::withMessages([
                'location_timestamp' => 'The location reading is stale. Obtain a fresh location and retry.',
            ]);
        }

        $message = implode("\n", [
            'attendance-v1',
            (string) $challenge->id,
            $challenge->nonce,
            (string) $user->id,
            (string) $device->id,
            $expectedOperation,
            number_format((float) $data['latitude'], 7, '.', ''),
            number_format((float) $data['longitude'], 7, '.', ''),
            number_format((float) $data['accuracy'], 2, '.', ''),
            $data['location_timestamp'],
        ]);

        $this->consumeAndVerify(
            $user,
            (int) $challenge->id,
            'attendance',
            $expectedOperation,
            $data['signature'],
            $device->public_key,
            static fn (): string => $message,
            $device
        );

        return $device;
    }

    private function createChallenge(
        User $user,
        ?StaffDevice $device,
        string $purpose,
        ?string $operation
    ): StaffDeviceChallenge {
        return StaffDeviceChallenge::create([
            'user_id' => $user->id,
            'staff_device_id' => $device?->id,
            'purpose' => $purpose,
            'operation' => $operation,
            'nonce' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes(2),
        ]);
    }

    private function consumeAndVerify(
        User $user,
        int $challengeId,
        string $purpose,
        ?string $operation,
        string $signature,
        string $publicKey,
        callable $message,
        ?StaffDevice $device = null
    ): void {
        DB::transaction(function () use (
            $user,
            $challengeId,
            $purpose,
            $operation,
            $signature,
            $publicKey,
            $message,
            $device
        ): void {
            if ($device) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $lockedDevice = StaffDevice::query()
                    ->whereKey($device->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();
                if (! $lockedDevice) {
                    throw ValidationException::withMessages([
                        'device' => 'This device is no longer authorized.',
                    ]);
                }
            }

            $challenge = StaffDeviceChallenge::query()
                ->whereKey($challengeId)
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->where('operation', $operation)
                ->when($device, fn ($query) => $query->where('staff_device_id', $device->id))
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->consumed_at || $challenge->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'challenge' => 'The challenge has expired or has already been used.',
                ]);
            }

            $key = openssl_pkey_get_public($publicKey);
            if (! $key instanceof OpenSSLAsymmetricKey || ! $this->isSupportedSigningKey($key)) {
                throw ValidationException::withMessages([
                    'public_key' => 'A valid P-256 or RSA-2048 public key is required.',
                ]);
            }

            $decodedSignature = base64_decode($signature, true);
            if ($decodedSignature === false || strlen($decodedSignature) > 256
                || openssl_verify($message($challenge), $decodedSignature, $key, OPENSSL_ALGO_SHA256) !== 1) {
                throw ValidationException::withMessages([
                    'signature' => 'Cryptographic proof could not be verified.',
                ]);
            }

            $challenge->forceFill(['consumed_at' => now()])->save();
        });
    }

    private function isSupportedSigningKey(OpenSSLAsymmetricKey $key): bool
    {
        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            return false;
        }

        return (($details['type'] ?? null) === OPENSSL_KEYTYPE_EC
                && ($details['ec']['curve_name'] ?? null) === 'prime256v1')
            || (($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA
                && ($details['bits'] ?? null) === 2048);
    }

    private function parseLocationTimestamp(string $timestamp): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.v\Z',
            $timestamp,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (! $parsed || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $parsed->format('Y-m-d\TH:i:s.v\Z') !== $timestamp) {
            throw ValidationException::withMessages([
                'location_timestamp' => 'A valid UTC location timestamp is required.',
            ]);
        }

        return $parsed;
    }
}
