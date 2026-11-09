<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\StaffDevice;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private array $devicePrivateKeys = [];

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Company One',
            'slug' => 'company-one',
        ]);
        $this->otherCompany = Company::create([
            'name' => 'Company Two',
            'slug' => 'company-two',
        ]);
    }

    public function test_company_admin_can_view_own_staff_but_not_another_company_staff(): void
    {
        $admin = $this->createUser($this->company, 'admin');
        $ownStaff = $this->createUser($this->company, 'staff');
        $otherStaff = $this->createUser($this->otherCompany, 'staff');

        $this->actingAs($admin)
            ->get(route('staff.show', $ownStaff))
            ->assertOk();

        $this->get(route('staff.show', $otherStaff))
            ->assertNotFound();
    }

    public function test_company_admin_dashboard_and_attendance_report_only_include_own_staff(): void
    {
        $admin = $this->createUser($this->company, 'admin');
        $ownStaff = $this->createUser($this->company, 'staff');
        $otherStaff = $this->createUser($this->otherCompany, 'staff');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSeeText($ownStaff->name)
            ->assertDontSeeText($otherStaff->name);

        $this->get('/admin/attendance')
            ->assertOk()
            ->assertSeeText($ownStaff->name)
            ->assertDontSeeText($otherStaff->name);
    }

    public function test_company_admin_cannot_assign_other_company_or_privileged_role_when_creating_staff(): void
    {
        $admin = $this->createUser($this->company, 'admin');
        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'name' => 'New Staff',
                'email' => 'new-staff@example.test',
                'phone' => '+255700000001',
                'employee_id' => 'EMP-NEW-1',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'super_admin',
                'company_id' => $this->otherCompany->id,
            ])
            ->assertCreated()
            ->assertJsonPath('staff.role', 'staff')
            ->assertJsonPath('staff.company_id', $this->company->id);

        $this->assertDatabaseMissing('users', [
            'email' => 'new-staff@example.test',
            'role' => 'super_admin',
        ]);

        Mail::fake();
        $this->actingAs($admin)
            ->postJson(route('admin.invitations.store'), [
                'email' => 'invalid-role@example.test',
                'role' => 'super_admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->actingAs($admin)
            ->postJson(route('admin.invitations.store'), [
                'email' => 'additional-admin@example.test',
                'role' => 'admin',
            ])
            ->assertOk();
        $this->assertDatabaseHas('staff_invitations', [
            'email' => 'additional-admin@example.test',
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_company_admin_cannot_edit_another_company_staff_or_promote_themselves(): void
    {
        $admin = $this->createUser($this->company, 'admin');
        $otherStaff = $this->createUser($this->otherCompany, 'staff');
        $this->actingAs($admin)
            ->put(route('staff.update', $otherStaff), [
                'name' => 'Changed Name',
                'email' => $otherStaff->email,
            ])
            ->assertNotFound();

        $this->delete(route('staff.destroy', $otherStaff))
            ->assertNotFound();

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/user', [
            'name' => $admin->name,
            'phone' => null,
            'role' => 'super_admin',
            'company_id' => $this->otherCompany->id,
        ])->assertOk();

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame($this->company->id, $admin->fresh()->company_id);
    }

    public function test_staff_cannot_read_another_staff_members_attendance(): void
    {
        $staff = $this->createUser($this->company, 'staff');
        $otherStaff = $this->createUser($this->company, 'staff');
        Attendance::create([
            'company_id' => $this->company->id,
            'user_id' => $otherStaff->id,
            'attendance_date' => '2026-10-17',
            'check_in_time' => '08:00:00',
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/attendance/history')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonCount(0, 'data.records');
    }

    public function test_suspended_company_is_denied_web_and_api_access_but_can_logout(): void
    {
        $staff = $this->createUser($this->company, 'staff');
        $this->company->status = Company::STATUS_SUSPENDED;
        $this->company->save();
        Sanctum::actingAs($staff);

        $this->get('/staff/dashboard')->assertForbidden();
        $this->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
        $this->postJson('/api/v1/logout')->assertOk();
    }

    public function test_missing_company_assignment_is_denied_for_protected_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_active_company_attendance_uses_only_its_own_office(): void
    {
        $staff = $this->createUser($this->company, 'staff');
        $office = $this->company->officeLocations()->create([
            'office_name' => 'Company One Office',
            'latitude' => 10,
            'longitude' => 10,
            'allowed_radius' => 100,
        ]);
        $this->otherCompany->officeLocations()->create([
            'office_name' => 'Company Two Office',
            'latitude' => 0,
            'longitude' => 0,
            'allowed_radius' => 100,
        ]);
        $this->registerDevice($staff);
        Sanctum::actingAs($staff);

        $this->postJson(
            '/api/v1/attendance/check-in',
            $this->signedAttendancePayload($staff, 0, 0)
        )->assertForbidden();

        $this->postJson(
            '/api/v1/attendance/check-in',
            $this->signedAttendancePayload($staff, 10, 10)
        )->assertCreated();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id,
            'company_id' => $this->company->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $staff->id,
            'company_id' => $this->otherCompany->id,
        ]);
        $this->assertDatabaseHas('office_locations', [
            'id' => $office->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_super_admin_can_manage_platform_companies_without_tenant_assignment(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->company->status = Company::STATUS_SUSPENDED;
        $this->company->save();
        Sanctum::actingAs($superAdmin);

        $this->getJson('/platform/companies')->assertOk();
        $this->postJson('/platform/companies', [
            'name' => 'New Platform Company',
            'slug' => 'new-platform-company',
        ])->assertCreated()
            ->assertJsonPath('company.status', Company::STATUS_ACTIVE);
    }

    public function test_super_admin_can_invite_an_initial_company_admin_safely(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($superAdmin);

        $this->postJson(route('platform.companies.admin-invitations.store', $this->company), [
            'email' => 'company-admin@example.test',
        ])->assertCreated();

        $invitation = StaffInvitation::where('email', 'company-admin@example.test')->firstOrFail();
        $this->assertSame('admin', $invitation->role);
        $this->assertSame($this->company->id, $invitation->company_id);

        $this->postJson('/api/v1/staff/register', [
            'token' => $invitation->token,
            'name' => 'Company Admin',
            'organization' => 'Untrusted Organization Label',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseHas('users', [
            'email' => 'company-admin@example.test',
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        $this->assertNotNull($invitation->fresh()->used_at);
    }

    public function test_staff_registration_can_complete_without_an_organization_value(): void
    {
        $invitation = $this->company->staffInvitations()->create([
            'email' => 'invited-staff@example.test',
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/v1/staff/register', [
            'token' => $invitation->token,
            'name' => 'Invited Staff',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'invited-staff@example.test');

        $this->assertDatabaseHas('users', [
            'email' => 'invited-staff@example.test',
            'organization' => null,
            'company_id' => $this->company->id,
        ]);
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->company_id = $company->id;
        $user->save();

        return $user;
    }

    private function registerDevice(User $user): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($privateKey);
        $details = openssl_pkey_get_details($privateKey);
        $this->assertNotFalse($details);
        $this->devicePrivateKeys[$user->id] = $privateKey;

        StaffDevice::create([
            'user_id' => $user->id,
            'platform' => 'android',
            'public_key' => $details['key'],
            'public_key_fingerprint' => hash('sha256', $details['key']),
            'status' => 'active',
            'approved_at' => now(),
        ]);
    }

    private function signedAttendancePayload(User $user, float $latitude, float $longitude): array
    {
        $device = StaffDevice::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
        $challenge = $this->postJson('/api/v1/attendance/challenges', [
            'operation' => 'check-in',
        ])->assertCreated()->json();
        $accuracy = '5.00';
        $latitudeText = number_format($latitude, 7, '.', '');
        $longitudeText = number_format($longitude, 7, '.', '');
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $message = implode("\n", [
            'attendance-v1',
            (string) $challenge['challenge_id'],
            $challenge['nonce'],
            (string) $user->id,
            (string) $device->id,
            'check-in',
            $latitudeText,
            $longitudeText,
            $accuracy,
            $timestamp,
        ]);
        $signature = '';
        $this->assertTrue(openssl_sign(
            $message,
            $signature,
            $this->devicePrivateKeys[$user->id],
            OPENSSL_ALGO_SHA256
        ));

        return [
            'latitude' => $latitudeText,
            'longitude' => $longitudeText,
            'accuracy' => $accuracy,
            'challenge_id' => $challenge['challenge_id'],
            'device_id' => $device->id,
            'location_timestamp' => $timestamp,
            'signature' => base64_encode($signature),
        ];
    }
}
