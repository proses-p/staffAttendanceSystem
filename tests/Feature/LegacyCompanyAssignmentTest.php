<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use App\Models\Company;
use App\Models\OfficeLocation;
use App\Models\StaffInvitation;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use App\Services\LegacyCompanyOwnershipAssigner;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LegacyCompanyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_records_remain_unassigned_during_dry_run_and_are_explicitly_assigned_with_audit(): void
    {
        $company = $this->company('assignment-target');
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $attendance = $this->attendance($staff);
        $leave = $this->leave($staff, $admin);
        $adminBefore = $admin->fresh()->only(['id', 'password', 'role', 'is_active']);
        $staffBefore = $staff->fresh()->only(['id', 'password', 'role', 'is_active']);

        $mapping = $this->emptyMapping();
        $mapping['records']['users'] = [$admin->id, $staff->id];
        $mapping['records']['attendances'] = [$attendance->id];
        $mapping['records']['staff_leave_records'] = [$leave->id];
        $assigner = app(LegacyCompanyOwnershipAssigner::class);

        $dryRun = $assigner->assign($company->id, $mapping, 'CHANGE-123', false);

        $this->assertSame(2, $dryRun['users']['assigned']);
        $this->assertSame(1, $dryRun['attendances']['assigned']);
        $this->assertSame(1, $dryRun['staff_leave_records']['assigned']);
        $this->assertNull($admin->fresh()->company_id);
        $this->assertNull($staff->fresh()->company_id);
        $this->assertNull($attendance->fresh()->company_id);
        $this->assertNull($leave->fresh()->company_id);
        $this->assertDatabaseCount('company_ownership_audits', 0);

        $assigner->assign($company->id, $mapping, 'CHANGE-123', true);

        $this->assertSame($company->id, $admin->fresh()->company_id);
        $this->assertSame($company->id, $staff->fresh()->company_id);
        $this->assertSame($adminBefore, $admin->fresh()->only(['id', 'password', 'role', 'is_active']));
        $this->assertSame($staffBefore, $staff->fresh()->only(['id', 'password', 'role', 'is_active']));
        $this->assertSame($company->id, $attendance->fresh()->company_id);
        $this->assertSame($staff->id, $attendance->fresh()->user_id);
        $this->assertSame($company->id, $leave->fresh()->company_id);
        $this->assertSame($staff->id, $leave->fresh()->user_id);
        $this->assertSame($admin->id, $leave->fresh()->recorded_by);
        $this->assertDatabaseCount('company_ownership_audits', 4);
        $this->assertDatabaseHas('company_ownership_audits', [
            'company_id' => $company->id,
            'record_type' => 'attendances',
            'record_id' => $attendance->id,
            'operator' => 'CHANGE-123',
        ]);
    }

    public function test_offices_settings_holidays_and_invitations_require_explicit_record_ids(): void
    {
        $company = $this->company('explicit-records');
        $office = OfficeLocation::create([
            'office_name' => 'Legacy Office',
            'latitude' => 1,
            'longitude' => 2,
            'allowed_radius' => 100,
        ]);
        $settings = AttendanceSetting::create(['expected_arrival_time' => '08:00:00']);
        $holiday = AttendanceHoliday::create([
            'holiday_date' => '2026-12-25',
            'name' => 'Holiday',
        ]);
        $invitation = StaffInvitation::create([
            'email' => 'invite@example.test',
            'token' => 'test-invitation-token',
            'expires_at' => now()->addDay(),
        ]);

        $mapping = $this->emptyMapping();
        $mapping['records']['office_locations'] = [$office->id];
        $mapping['records']['attendance_settings'] = [$settings->id];
        $mapping['records']['attendance_holidays'] = [$holiday->id];
        $mapping['records']['staff_invitations'] = [$invitation->id];

        app(LegacyCompanyOwnershipAssigner::class)->assign($company->id, $mapping, 'CHANGE-123B', true);

        $this->assertSame($company->id, $office->fresh()->company_id);
        $this->assertSame($company->id, $settings->fresh()->company_id);
        $this->assertSame($company->id, $holiday->fresh()->company_id);
        $this->assertSame($company->id, $invitation->fresh()->company_id);
    }

    public function test_attendance_for_a_user_in_another_company_is_rejected(): void
    {
        $target = $this->company('attendance-target');
        $other = $this->company('attendance-other');
        $staff = $this->assignedUser($other, 'staff');
        $attendance = $this->attendance($staff);
        $mapping = $this->emptyMapping();
        $mapping['records']['attendances'] = [$attendance->id];

        $this->expectException(DomainException::class);
        app(LegacyCompanyOwnershipAssigner::class)->assign($target->id, $mapping, 'CHANGE-124', true);
    }

    public function test_leave_for_a_user_in_another_company_is_rejected(): void
    {
        $target = $this->company('leave-target');
        $other = $this->company('leave-other');
        $staff = $this->assignedUser($other, 'staff');
        $leave = $this->leave($staff);
        $mapping = $this->emptyMapping();
        $mapping['records']['staff_leave_records'] = [$leave->id];

        $this->expectException(DomainException::class);
        app(LegacyCompanyOwnershipAssigner::class)->assign($target->id, $mapping, 'CHANGE-125', true);
    }

    public function test_leave_recorded_by_another_company_is_rejected(): void
    {
        $target = $this->company('recorder-target');
        $other = $this->company('recorder-other');
        $staff = $this->assignedUser($target, 'staff');
        $recorder = $this->assignedUser($other, 'admin');
        $leave = $this->leave($staff, $recorder);
        $mapping = $this->emptyMapping();
        $mapping['records']['staff_leave_records'] = [$leave->id];

        $this->expectException(DomainException::class);
        app(LegacyCompanyOwnershipAssigner::class)->assign($target->id, $mapping, 'CHANGE-126', true);
    }

    public function test_ambiguous_or_duplicate_mapping_fails_before_writing(): void
    {
        $company = $this->company('mapping-validation');
        $user = User::factory()->create();
        $mapping = $this->emptyMapping();
        unset($mapping['records']['attendance_holidays']);

        try {
            app(LegacyCompanyOwnershipAssigner::class)->assign($company->id, $mapping, 'CHANGE-127', true);
            $this->fail('A mapping that omitted a record type was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertNull($user->fresh()->company_id);
        }

        $mapping = $this->emptyMapping();
        $mapping['records']['users'] = [$user->id, $user->id];

        try {
            app(LegacyCompanyOwnershipAssigner::class)->assign($company->id, $mapping, 'CHANGE-127', true);
            $this->fail('A mapping with duplicate IDs was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertNull($user->fresh()->company_id);
        }
    }

    public function test_every_unassigned_record_must_be_assigned_or_explicitly_deferred(): void
    {
        $company = $this->company('complete-mapping');
        $assigned = User::factory()->create();
        $deferred = User::factory()->create();
        $mapping = $this->emptyMapping();
        $mapping['records']['users'] = [$assigned->id];

        try {
            app(LegacyCompanyOwnershipAssigner::class)->assign($company->id, $mapping, 'CHANGE-127A', true);
            $this->fail('The mapping omitted an unassigned user without an explicit disposition.');
        } catch (DomainException) {
            $this->assertNull($assigned->fresh()->company_id);
            $this->assertNull($deferred->fresh()->company_id);
        }

        $mapping['deferred']['users'] = [$deferred->id];
        app(LegacyCompanyOwnershipAssigner::class)->assign($company->id, $mapping, 'CHANGE-127A', true);

        $this->assertSame($company->id, $assigned->fresh()->company_id);
        $this->assertNull($deferred->fresh()->company_id);
    }

    public function test_rerunning_assignment_does_not_overwrite_or_duplicate_audit_entries(): void
    {
        $company = $this->company('idempotent-target');
        $user = User::factory()->create();
        $mapping = $this->emptyMapping();
        $mapping['records']['users'] = [$user->id];
        $assigner = app(LegacyCompanyOwnershipAssigner::class);

        $assigner->assign($company->id, $mapping, 'CHANGE-128', true);
        $secondRun = $assigner->assign($company->id, $mapping, 'CHANGE-128', true);

        $this->assertSame(0, $secondRun['users']['assigned']);
        $this->assertSame(0, $secondRun['users']['deferred']);
        $this->assertSame($company->id, $user->fresh()->company_id);
        $this->assertDatabaseCount('company_ownership_audits', 1);

        $otherCompany = $this->company('idempotent-other');
        $this->expectException(DomainException::class);
        $assigner->assign($otherCompany->id, $mapping, 'CHANGE-129', true);
    }

    public function test_attendance_and_leave_history_fields_are_not_changed_by_assignment(): void
    {
        $company = $this->company('history-preserved');
        $staff = User::factory()->create(['role' => 'staff']);
        $attendance = $this->attendance($staff);
        $leave = $this->leave($staff);
        $attendanceBefore = $attendance->fresh()->getAttributes();
        $leaveBefore = $leave->fresh()->getAttributes();

        $mapping = $this->emptyMapping();
        $mapping['records']['users'] = [$staff->id];
        $mapping['records']['attendances'] = [$attendance->id];
        $mapping['records']['staff_leave_records'] = [$leave->id];

        app(LegacyCompanyOwnershipAssigner::class)->assign($company->id, $mapping, 'CHANGE-130', true);

        $attendanceAfter = $attendance->fresh()->getAttributes();
        $leaveAfter = $leave->fresh()->getAttributes();
        unset($attendanceBefore['company_id'], $attendanceAfter['company_id']);
        unset($leaveBefore['company_id'], $leaveAfter['company_id']);

        $this->assertSame($attendanceBefore, $attendanceAfter);
        $this->assertSame($leaveBefore, $leaveAfter);
    }

    /**
     * @return array{records: array<string, array<int, int>>, deferred: array<string, array<int, int>>}
     */
    private function emptyMapping(): array
    {
        $emptyRecords = [
            'users' => [],
            'office_locations' => [],
            'attendance_settings' => [],
            'attendance_holidays' => [],
            'staff_invitations' => [],
            'staff_leave_records' => [],
            'attendances' => [],
        ];

        return [
            'records' => $emptyRecords,
            'deferred' => $emptyRecords,
        ];
    }

    private function company(string $slug): Company
    {
        return Company::create(['name' => $slug, 'slug' => $slug]);
    }

    private function assignedUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->company_id = $company->id;
        $user->save();

        return $user;
    }

    private function attendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-10-23',
            'check_in_time' => '08:00:00',
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
    }

    private function leave(User $user, ?User $recorder = null): StaffLeaveRecord
    {
        return StaffLeaveRecord::create([
            'user_id' => $user->id,
            'start_date' => '2026-10-24',
            'end_date' => '2026-10-25',
            'recorded_by' => $recorder?->id,
        ]);
    }
}
