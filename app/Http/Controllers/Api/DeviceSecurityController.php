<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenSSLAsymmetricKey;

class DeviceSecurityController extends Controller
{
    public function syncRecoveredKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:128'],
            'new_public_key' => ['required', 'string', 'max:10000'],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $publicKey = $this->decodeRsaPublicKey($validated['new_public_key']);
        $fingerprint = hash('sha256', $publicKey);

        $device = DB::transaction(function () use ($validated, $user, $publicKey, $fingerprint): StaffDevice {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($validated['current_password'], $lockedUser->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'current_password' => 'The current password is incorrect.',
                ]);
            }

            $devices = StaffDevice::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            if ($devices->count() !== 1) {
                throw ValidationException::withMessages([
                    'device' => 'Exactly one active device is required for key recovery.',
                ]);
            }

            /** @var StaffDevice $device */
            $device = $devices->first();
            if ($device->device_uuid !== null
                && ! hash_equals($device->device_uuid, $validated['device_uuid'])) {
                throw ValidationException::withMessages([
                    'device_uuid' => 'This device identifier does not match the active registration.',
                ]);
            }

            if (StaffDevice::query()
                ->where('public_key_fingerprint', $fingerprint)
                ->whereKeyNot($device->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'new_public_key' => 'This signing key is already registered to another device.',
                ]);
            }

            $device->forceFill([
                'device_uuid' => $validated['device_uuid'],
                'public_key' => $publicKey,
                'public_key_fingerprint' => $fingerprint,
            ])->save();

            return $device;
        });

        return response()->json([
            'message' => 'The active device signing key was recovered.',
            'device_id' => $device->id,
            'status' => $device->status,
        ]);
    }

    private function decodeRsaPublicKey(string $encodedKey): string
    {
        $der = base64_decode($encodedKey, true);
        if ($der === false || base64_encode($der) !== $encodedKey) {
            throw ValidationException::withMessages([
                'new_public_key' => 'The recovered public key must be canonical Base64-encoded X.509 data.',
            ]);
        }

        $publicKey = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($publicKey);
        if (! $key instanceof OpenSSLAsymmetricKey || ! $this->isRsa2048Key($key)) {
            throw ValidationException::withMessages([
                'new_public_key' => 'A valid RSA-2048 X.509 public key is required.',
            ]);
        }

        return $publicKey;
    }

    private function isRsa2048Key(OpenSSLAsymmetricKey $key): bool
    {
        $details = openssl_pkey_get_details($key);

        return $details !== false
            && ($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA
            && ($details['bits'] ?? null) === 2048;
    }
}
