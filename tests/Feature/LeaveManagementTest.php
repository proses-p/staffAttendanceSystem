<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Leave Test Company',
            'slug' => 'leave-test-company',
        ]);
    }

    public function test_staff_request_is_pending_and_owned_by_the_authenticated_staff_member(): void
    {
        $staff = $this->createCompanyUser();
        $otherStaff = $this->createCompanyUser();
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/leave-requests', [
            'user_id' => $otherStaff->id,
            'status' => 'approved',
            'leave_type' => 'Leave',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'reason' => 'A family commitment.',
        ])->assertCreated()
            ->assertJsonPath('leave_request.status', 'pending')
            ->assertJsonPath('leave_request.reason', 'A family commitment.');

        $this->assertDatabaseHas('staff_leave_records', [
            'user_id' => $staff->id,
            'company_id' => $this->company->id,
            'leave_type' => 'Leave',
            'start_date' => '2026-11-02 00:00:00',
            'end_date' => '2026-11-03 00:00:00',
            'reason' => 'A family commitment.',
            'status' => 'pending',
            'request_source' => 'staff_request',
            'recorded_by' => null,
        ]);
        $this->assertDatabaseMissing('staff_leave_records', [
            'user_id' => $otherStaff->id,
            'reason' => 'A family commitment.',
        ]);

        $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'Leave',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'reason' => 'Overlapping dates.',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('staff_leave_records', 1);
    }

    public function test_staff_request_validates_reason_leave_type_and_date_range(): void
    {
        Sanctum::actingAs($this->createCompanyUser());

        $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'Sick leave',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-04',
            'reason' => '   ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_type', 'end_date', 'reason']);

        $this->assertDatabaseCount('staff_leave_records', 0);
    }

    public function test_staff_can_only_read_their_own_requests_and_admins_cannot_use_staff_api(): void
    {
        $staff = $this->createCompanyUser();
        $otherStaff = $this->createCompanyUser();
        $ownRequest = $this->createLeave($staff, [
            'reason' => 'My request.',
            'request_source' => 'staff_request',
        ]);
        $this->createLeave($otherStaff, [
            'reason' => 'Private request.',
            'request_source' => 'staff_request',
        ]);

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/leave-requests')
            ->assertOk()
            ->assertJsonCount(1, 'leave_requests')
            ->assertJsonPath('leave_requests.0.id', $ownRequest->id)
            ->assertJsonMissing(['reason' => 'Private request.']);

        Sanctum::actingAs($this->createCompanyUser(['role' => 'admin']));
        $this->getJson('/api/v1/leave-requests')->assertForbidden();
    }

    public function test_admin_decides_pending_requests_once_with_an_optional_note(): void
    {
        $admin = $this->createCompanyUser(['role' => 'admin']);
        $staff = $this->createCompanyUser();
        $approvedRequest = $this->createLeave($staff, [
            'request_source' => 'staff_request',
        ]);
        $rejectedRequest = $this->createLeave($staff, [
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-10',
            'request_source' => 'staff_request',
        ]);

        $this->actingAs($admin)
            ->get('/admin/time-settings')
            ->assertOk()
            ->assertSee('Staff request')
            ->assertSee('View request details')
            ->assertSee('Filter requests');

        $this->patch(route('admin.leave-records.update', $approvedRequest), [
            'status' => 'approved',
            'decision_note' => 'Approved by your manager.',
        ])->assertRedirect(route('admin.time-settings.index'));

        $this->assertDatabaseHas('staff_leave_records', [
            'id' => $approvedRequest->id,
            'status' => 'approved',
            'decision_note' => 'Approved by your manager.',
        ]);

        $this->patch(route('admin.leave-records.update', $approvedRequest), [
            'status' => 'rejected',
        ])->assertSessionHasErrors('status');

        $this->patch(route('admin.leave-records.update', $rejectedRequest), [
            'status' => 'rejected',
            'decision_note' => 'Please choose another date.',
        ])->assertRedirect(route('admin.time-settings.index'));

        $this->assertDatabaseHas('staff_leave_records', [
            'id' => $rejectedRequest->id,
            'status' => 'rejected',
            'decision_note' => 'Please choose another date.',
        ]);
    }

    public function test_admin_cannot_decide_a_request_from_another_company(): void
    {
        $admin = $this->createCompanyUser(['role' => 'admin']);
        $otherCompany = Company::create([
            'name' => 'Other Leave Company',
            'slug' => 'other-leave-company',
        ]);
        $otherStaff = User::factory()->create(['role' => 'staff']);
        $otherStaff->company_id = $otherCompany->id;
        $otherStaff->save();
        $request = $otherCompany->staffLeaveRecords()->create([
            'user_id' => $otherStaff->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'status' => 'pending',
            'request_source' => 'staff_request',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.leave-records.update', $request), [
                'status' => 'approved',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('staff_leave_records', [
            'id' => $request->id,
            'status' => 'pending',
        ]);
    }

    public function test_only_approved_leave_is_excluded_from_attendance_statistics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00', 'Africa/Dar_es_salaam'));
        $admin = $this->createCompanyUser(['role' => 'admin']);
        $staff = $this->createCompanyUser();
        $this->company->attendanceSetting()->create([
            'working_days' => [4],
        ]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'Leave',
            'start_date' => '2026-10-22',
            'end_date' => '2026-10-22',
            'reason' => 'Rejected day.',
        ])->assertCreated();
        $rejectedRequest = StaffLeaveRecord::query()->where('user_id', $staff->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.leave-records.update', $rejectedRequest), [
                'status' => 'rejected',
            ])
            ->assertRedirect(route('admin.time-settings.index'));

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'Leave',
            'start_date' => '2026-10-29',
            'end_date' => '2026-10-29',
            'reason' => 'Request awaiting a decision.',
        ])->assertCreated();
        $pendingRequest = StaffLeaveRecord::query()
            ->where('user_id', $staff->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.approved_leave_days', 0)
            ->assertJsonPath('statistics.eligible_scheduled_days', 5)
            ->assertJsonPath('statistics.absent_days', 5);

        $this->actingAs($admin)
            ->patch(route('admin.leave-records.update', $pendingRequest), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.time-settings.index'));

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.approved_leave_days', 1)
            ->assertJsonPath('statistics.eligible_scheduled_days', 4)
            ->assertJsonPath('statistics.absent_days', 4);
    }

    private function createLeave(User $staff, array $attributes = []): StaffLeaveRecord
    {
        return $this->company->staffLeaveRecords()->create(array_replace([
            'user_id' => $staff->id,
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-06',
            'status' => 'pending',
            'leave_type' => 'Leave',
            'reason' => 'Personal reason.',
        ], $attributes));
    }

    private function createCompanyUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_replace([
            'role' => 'staff',
        ], $attributes));
        $user->company_id = $this->company->id;
        $user->save();

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
