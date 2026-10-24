<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create([
            'name' => 'Dashboard Test Company',
            'slug' => 'dashboard-test-company',
        ]);
    }

    public function test_dashboard_statistics_apply_configured_schedule_grace_overtime_holidays_and_leave(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 18:00:00', 'Africa/Dar_es_salaam'));
        $employee = $this->createCompanyUser();
        Sanctum::actingAs($employee);

        $this->company->attendanceSetting()->create([
            'expected_arrival_time' => '09:00',
            'expected_work_minutes' => 480,
            'grace_period_minutes' => 10,
            'working_days' => [1, 2, 3, 4, 5],
        ]);
        $this->company->attendanceHolidays()->create([
            'holiday_date' => '2026-10-05',
            'name' => 'Organization holiday',
        ]);
        $this->company->staffLeaveRecords()->create([
            'user_id' => $employee->id,
            'start_date' => '2026-10-06',
            'end_date' => '2026-10-06',
            'status' => 'approved',
        ]);

        $this->createAttendance($employee, '2026-10-01', '09:10:00', '18:10:00');
        $this->createAttendance($employee, '2026-10-02', '09:11:00', '17:11:00');
        $this->createAttendance($employee, '2026-10-03', '09:00:00', '20:00:00');
        $this->createAttendance($employee, '2026-10-07', '09:10:00', null);
        $this->createAttendance($employee, '2026-10-08', '08:45:00', '16:45:00');

        $otherEmployee = $this->createCompanyUser();
        $this->createAttendance($otherEmployee, '2026-10-01', '08:00:00', '20:00:00');

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.total_worked_minutes', 2160)
            ->assertJsonPath('statistics.overtime_minutes', 60)
            ->assertJsonPath('statistics.on_time_rate_percent', 60)
            ->assertJsonPath('statistics.attendance_rate_percent', 80)
            ->assertJsonPath('statistics.approved_leave_days', 1)
            ->assertJsonPath('statistics.incomplete_days', 1)
            ->assertJsonPath('statistics.absent_days', 1)
            ->assertJsonPath('statistics.eligible_scheduled_days', 5);
    }

    public function test_open_shift_is_not_worked_or_overtime_and_today_is_not_marked_absent_before_grace_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:05:00', 'Africa/Dar_es_salaam'));
        $employee = $this->createCompanyUser();
        Sanctum::actingAs($employee);
        $this->company->attendanceSetting()->create([
            'expected_arrival_time' => '09:00',
            'expected_work_minutes' => 480,
            'grace_period_minutes' => 10,
            'working_days' => [1, 2, 3, 4, 5],
        ]);
        $this->createAttendance($employee, '2026-10-01', '09:04:00', null);
        $this->assertNotNull($employee->attendances()->firstOrFail()->check_in_at);
        $this->assertSame([1, 2, 3, 4, 5], AttendanceSetting::firstOrFail()->working_days);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('attendance_state', 'in_progress')
            ->assertJsonPath('statistics.total_worked_minutes', 0)
            ->assertJsonPath('statistics.overtime_minutes', 0)
            ->assertJsonPath('statistics.eligible_scheduled_days', 1)
            ->assertJsonPath('statistics.attendance_rate_percent', 100)
            ->assertJsonPath('statistics.on_time_rate_percent', 100)
            ->assertJsonPath('statistics.incomplete_days', 0)
            ->assertJsonPath('statistics.absent_days', 0);
    }

    public function test_missing_policy_keeps_only_completed_hours_available_and_empty_history_is_safe(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 18:00:00', 'Africa/Dar_es_salaam'));
        Sanctum::actingAs($this->createCompanyUser());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.total_worked_minutes', 0)
            ->assertJsonPath('statistics.overtime_minutes', null)
            ->assertJsonPath('statistics.on_time_rate_percent', null)
            ->assertJsonPath('statistics.attendance_rate_percent', null)
            ->assertJsonPath('statistics.approved_leave_days', null)
            ->assertJsonPath('statistics.incomplete_days', null)
            ->assertJsonPath('statistics.has_attendance_history', false);
    }

    public function test_zero_eligible_days_returns_null_rates_without_division_by_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 18:00:00', 'Africa/Dar_es_salaam'));
        Sanctum::actingAs($this->createCompanyUser());
        $this->company->attendanceSetting()->create([
            'expected_arrival_time' => '09:00',
            'expected_work_minutes' => 480,
            'grace_period_minutes' => 10,
            'working_days' => [7],
        ]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.eligible_scheduled_days', 0)
            ->assertJsonPath('statistics.attendance_rate_percent', null)
            ->assertJsonPath('statistics.on_time_rate_percent', null);
    }

    public function test_database_unique_constraint_prevents_duplicate_daily_attendance(): void
    {
        $employee = $this->createCompanyUser();
        $this->createAttendance($employee, '2026-10-01', '09:00:00', '17:00:00');

        try {
            $this->createAttendance($employee, '2026-10-01', '09:30:00', '17:30:00');
            $this->fail('A second attendance record for the same employee and day was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('attendances', 1);
        }
    }

    public function test_attendance_policy_and_leave_management_are_admin_only(): void
    {
        $staff = $this->createCompanyUser(['role' => 'staff']);
        $this->actingAs($staff)
            ->get('/admin/time-settings')
            ->assertForbidden();

        $admin = $this->createCompanyUser(['role' => 'admin']);
        $this->actingAs($admin)
            ->get('/admin/time-settings')
            ->assertOk();
    }

    public function test_admin_can_save_policy_and_manage_holidays_and_approved_leave(): void
    {
        $admin = $this->createCompanyUser(['role' => 'admin']);
        $employee = $this->createCompanyUser(['role' => 'staff']);
        $this->actingAs($admin);

        $this->post('/admin/time-settings', [
            'expected_arrival_time' => '08:30',
            'expected_work_hours' => '7.5',
            'grace_period_minutes' => 5,
            'working_days' => ['1', '2', '3', '4', '5'],
        ])->assertRedirect(route('admin.time-settings.index'));

        $this->assertDatabaseHas('attendance_settings', [
            'id' => 1,
            'expected_work_minutes' => 450,
            'grace_period_minutes' => 5,
        ]);
        $this->assertSame([1, 2, 3, 4, 5], AttendanceSetting::firstOrFail()->working_days);

        $this->post('/admin/holidays', [
            'holiday_date' => '2026-12-25',
            'name' => 'Organization holiday',
        ])->assertRedirect(route('admin.time-settings.index'));
        $this->assertDatabaseHas('attendance_holidays', ['holiday_date' => '2026-12-25 00:00:00']);

        $this->post('/admin/leave-records', [
            'user_id' => $employee->id,
            'start_date' => '2026-12-28',
            'end_date' => '2026-12-29',
            'status' => 'approved',
        ])->assertRedirect(route('admin.time-settings.index'));
        $this->assertDatabaseHas('staff_leave_records', [
            'user_id' => $employee->id,
            'start_date' => '2026-12-28 00:00:00',
            'status' => 'approved',
            'recorded_by' => $admin->id,
        ]);

        $this->post('/admin/leave-records', [
            'user_id' => $employee->id,
            'start_date' => '2026-12-28',
            'end_date' => '2026-12-28',
            'status' => 'pending',
        ])->assertSessionHasErrors('start_date');
    }

    public function test_admin_cannot_approve_full_day_leave_over_an_attendance_record(): void
    {
        $admin = $this->createCompanyUser(['role' => 'admin']);
        $employee = $this->createCompanyUser(['role' => 'staff']);
        $this->createAttendance($employee, '2026-10-01', '09:00:00', '17:00:00');
        $leave = $this->company->staffLeaveRecords()->create([
            'user_id' => $employee->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.leave-records.update', $leave), [
                'status' => 'approved',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('staff_leave_records', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);
    }

    private function createAttendance(
        User $user,
        string $date,
        string $checkIn,
        ?string $checkOut
    ): Attendance {
        return $this->company->attendances()->create([
            'user_id' => $user->id,
            'attendance_date' => $date,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
    }

    private function createCompanyUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_replace(['role' => 'staff'], $attributes));
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
