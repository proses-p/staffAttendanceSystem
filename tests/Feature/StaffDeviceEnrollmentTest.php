<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StaffDevice;
use App\Models\StaffDeviceChallenge;
use App\Models\User;
use App\Services\StaffDeviceProofService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffDeviceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create([
            'name' => 'Device Test Company',
            'slug' => 'device-test-company',
        ]);
    }

    public function test_enrollment_requires_a_valid_single_use_proof_of_possession(): void
    {
        $staff = $this->staffUser();
        Sanctum::actingAs($staff);
        $pair = $this->keyPair();
        $challenge = $this->postJson('/api/v1/devices/enrollment-challenge')
            ->assertCreated()
            ->json();
        $signature = $this->signature(
            implode("\n", [
                'staff-device-enroll-v1',
                (string) $challenge['challenge_id'],
                $challenge['nonce'],
                (string) $staff->id,
            ]),
            $pair['private']
        );
        $request = [
            'platform' => 'android',
            'public_key' => $pair['public'],
            'challenge_id' => $challenge['challenge_id'],
            'signature' => base64_encode($signature),
        ];

        $this->postJson('/api/v1/devices/enroll', $request)
            ->assertCreated()
            ->assertJsonPath('device.status', 'pending');
        $this->assertSame(1, StaffDevice::count());
        $this->assertNull(StaffDevice::firstOrFail()->approved_at);

        $this->postJson('/api/v1/devices/enroll', $request)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
    }

    public function test_enrollment_rejects_invalid_proof_and_requires_staff_authentication(): void
    {
        $staff = $this->staffUser();
        Sanctum::actingAs($staff);
        $pair = $this->keyPair();
        $challenge = $this->postJson('/api/v1/devices/enrollment-challenge')
            ->assertCreated()
            ->json();

        $this->postJson('/api/v1/devices/enroll', [
            'platform' => 'android',
            'public_key' => $pair['public'],
            'challenge_id' => $challenge['challenge_id'],
            'signature' => base64_encode(random_bytes(64)),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('signature');
        $this->assertSame(0, StaffDevice::count());

        auth()->forgetGuards();
        $this->postJson('/api/v1/devices/enrollment-challenge')->assertUnauthorized();
    }

    public function test_only_company_admin_can_approve_and_one_active_device_is_enforced(): void
    {
        $staff = $this->staffUser();
        $pending = $this->createPendingDevice($staff);
        Sanctum::actingAs($staff);

        $this->postJson("/admin/devices/{$pending->id}/approve")->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->company_id = $this->company->id;
        $admin->save();
        Sanctum::actingAs($admin);
        $this->from('/admin/devices')->post("/admin/devices/{$pending->id}/approve")
            ->assertRedirect('/admin/devices');
        $this->assertSame('active', $pending->fresh()->status);
        $this->assertSame($admin->id, $pending->fresh()->approved_by);

        Sanctum::actingAs($staff);
        $secondPending = $this->createPendingDevice($staff);
        Sanctum::actingAs($admin);
        $this->from('/admin/devices')->post("/admin/devices/{$secondPending->id}/approve")
            ->assertSessionHasErrors('device');
        $this->assertSame('pending', $secondPending->fresh()->status);
    }

    public function test_revocation_is_audited_and_immediately_blocks_new_attendance_challenges(): void
    {
        $staff = $this->staffUser();
        $device = $this->createPendingDevice($staff);
        $device->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->company_id = $this->company->id;
        $admin->save();
        Sanctum::actingAs($admin);

        $this->from('/admin/devices')->post("/admin/devices/{$device->id}/revoke")
            ->assertRedirect('/admin/devices');
        $this->assertSame('revoked', $device->fresh()->status);
        $this->assertSame($admin->id, $device->fresh()->revoked_by);
        $this->assertNotNull($device->fresh()->revoked_at);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/challenges', [
            'operation' => 'check-in',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('device');
    }

    public function test_expired_enrollment_challenge_cannot_register_a_device(): void
    {
        $staff = $this->staffUser();
        Sanctum::actingAs($staff);
        $pair = $this->keyPair();
        $challenge = $this->postJson('/api/v1/devices/enrollment-challenge')
            ->assertCreated()
            ->json();
        $signature = $this->signature(
            implode("\n", [
                'staff-device-enroll-v1',
                (string) $challenge['challenge_id'],
                $challenge['nonce'],
                (string) $staff->id,
            ]),
            $pair['private']
        );
        Carbon::setTestNow(now()->addMinutes(3));

        $this->postJson('/api/v1/devices/enroll', [
            'platform' => 'android',
            'public_key' => $pair['public'],
            'challenge_id' => $challenge['challenge_id'],
            'signature' => base64_encode($signature),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
        $this->assertSame(0, StaffDevice::count());
    }

    public function test_recovered_rsa_key_replaces_only_the_users_active_device_key(): void
    {
        $staff = $this->staffUser();
        $oldKeyPair = $this->keyPair();
        $device = StaffDevice::create([
            'user_id' => $staff->id,
            'platform' => 'android',
            'device_uuid' => 'android-device-123',
            'public_key' => $oldKeyPair['public'],
            'public_key_fingerprint' => hash('sha256', $oldKeyPair['public']),
            'status' => 'active',
            'approved_at' => now(),
        ]);
        $newKeyPair = $this->rsaKeyPair();
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/device/sync-recovered-key', [
            'device_uuid' => 'android-device-123',
            'new_public_key' => $newKeyPair['encoded_public'],
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonPath('device_id', $device->id)
            ->assertJsonPath('status', 'active');

        $device->refresh();
        $this->assertSame($newKeyPair['public'], $device->public_key);
        $this->assertSame(hash('sha256', $newKeyPair['public']), $device->public_key_fingerprint);
        $this->assertSame('android-device-123', $device->device_uuid);
        $this->assertSame('active', $device->status);

        $challenge = $this->postJson('/api/v1/sync/challenge', [])->assertCreated()->json();
        $payloadHash = hash('sha256', '[]');
        $signature = $this->signature(
            implode("\n", [
                'field-sync-v1',
                (string) $challenge['challenge_id'],
                $challenge['nonce'],
                (string) $staff->id,
                (string) $challenge['device_id'],
                $payloadHash,
            ]),
            $newKeyPair['private']
        );
        app(StaffDeviceProofService::class)->verifyFieldSync(
            $staff,
            $challenge['challenge_id'],
            $challenge['device_id'],
            $payloadHash,
            base64_encode($signature)
        );
        $this->assertNotNull(
            StaffDeviceChallenge::findOrFail($challenge['challenge_id'])->consumed_at
        );
    }

    public function test_recovered_key_requires_correct_password_and_matching_device_identity(): void
    {
        $staff = $this->staffUser();
        $oldKeyPair = $this->keyPair();
        $device = StaffDevice::create([
            'user_id' => $staff->id,
            'platform' => 'android',
            'device_uuid' => 'android-device-123',
            'public_key' => $oldKeyPair['public'],
            'public_key_fingerprint' => hash('sha256', $oldKeyPair['public']),
            'status' => 'active',
            'approved_at' => now(),
        ]);
        $newKeyPair = $this->rsaKeyPair();
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/device/sync-recovered-key', [
            'device_uuid' => 'android-device-123',
            'new_public_key' => $newKeyPair['encoded_public'],
            'current_password' => 'incorrect',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->postJson('/api/v1/device/sync-recovered-key', [
            'device_uuid' => 'different-device',
            'new_public_key' => $newKeyPair['encoded_public'],
            'current_password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('device_uuid');

        $this->assertSame($oldKeyPair['public'], $device->fresh()->public_key);
    }

    private function staffUser(): User
    {
        $user = User::factory()->create(['role' => 'staff']);
        $user->company_id = $this->company->id;
        $user->save();

        return $user;
    }

    private function createPendingDevice(User $user): StaffDevice
    {
        $pair = $this->keyPair();

        return StaffDevice::create([
            'user_id' => $user->id,
            'platform' => 'android',
            'public_key' => $pair['public'],
            'public_key_fingerprint' => hash('sha256', $pair['public']),
            'status' => 'pending',
        ]);
    }

    private function keyPair(): array
    {
        $private = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertNotFalse($details);

        return ['private' => $private, 'public' => $details['key']];
    }

    private function rsaKeyPair(): array
    {
        $private = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertNotFalse($details);
        $body = preg_replace(
            '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/',
            '',
            $details['key']
        );
        $this->assertIsString($body);

        return [
            'private' => $private,
            'public' => $details['key'],
            'encoded_public' => $body,
        ];
    }

    private function signature(string $message, \OpenSSLAsymmetricKey $private): string
    {
        $signature = '';
        $this->assertTrue(openssl_sign($message, $signature, $private, OPENSSL_ALGO_SHA256));

        return $signature;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
