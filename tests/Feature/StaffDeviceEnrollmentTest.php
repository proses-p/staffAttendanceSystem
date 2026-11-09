<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StaffDevice;
use App\Models\User;
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
