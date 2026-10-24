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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyOwnershipSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_has_canonical_identity_status_and_tenant_relationships(): void
    {
        $company = Company::create([
            'name' => 'Example Company',
            'slug' => 'example-company',
        ]);

        $company->refresh();

        $this->assertSame(Company::STATUS_ACTIVE, $company->status);
        $this->assertSame('example-company', $company->slug);
        $this->assertTrue($company->users()->getRelated() instanceof User);
        $this->assertTrue($company->officeLocations()->getRelated() instanceof OfficeLocation);
        $this->assertTrue($company->attendanceSetting()->getRelated() instanceof AttendanceSetting);
        $this->assertTrue($company->attendanceHolidays()->getRelated() instanceof AttendanceHoliday);
        $this->assertTrue($company->staffInvitations()->getRelated() instanceof StaffInvitation);
        $this->assertTrue($company->staffLeaveRecords()->getRelated() instanceof StaffLeaveRecord);
        $this->assertTrue($company->attendances()->getRelated() instanceof Attendance);
        $this->assertFalse($company->isFillable('status'));
    }

    public function test_tenant_foreign_keys_are_nullable_and_reference_companies(): void
    {
        $tenantTables = [
            'users',
            'office_locations',
            'attendance_settings',
            'attendance_holidays',
            'staff_invitations',
            'staff_leave_records',
            'attendances',
        ];

        foreach ($tenantTables as $tenantTable) {
            $column = collect(Schema::getColumns($tenantTable))
                ->firstWhere('name', 'company_id');

            $this->assertNotNull($column, "{$tenantTable}.company_id is missing.");
            $this->assertTrue($column['nullable'], "{$tenantTable}.company_id must remain nullable for safe backfill.");

            $foreignKey = collect(Schema::getForeignKeys($tenantTable))
                ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['company_id']);

            $this->assertNotNull($foreignKey, "{$tenantTable}.company_id foreign key is missing.");
            $this->assertSame('companies', $foreignKey['foreign_table']);
        }
    }

    public function test_user_company_assignment_and_role_are_not_mass_assignable(): void
    {
        $models = [
            new User,
            new OfficeLocation,
            new Attendance,
            new AttendanceSetting,
            new AttendanceHoliday,
            new StaffInvitation,
            new StaffLeaveRecord,
        ];

        foreach ($models as $model) {
            $model->fill(['company_id' => 123]);

            $this->assertNull($model->company_id);
            $this->assertFalse($model->isFillable('company_id'));
        }

        $user = new User;
        $user->fill([
            'name' => 'Staff Member',
            'role' => 'admin',
        ]);

        $this->assertSame('Staff Member', $user->name);
        $this->assertNull($user->role);
        $this->assertFalse($user->isFillable('role'));
    }

    public function test_existing_global_user_email_and_employee_id_uniqueness_are_preserved(): void
    {
        User::factory()->create([
            'email' => 'employee@example.test',
            'employee_id' => 'EMP-001',
        ]);

        foreach ([
            ['email' => 'employee@example.test', 'employee_id' => 'EMP-002'],
            ['email' => 'another@example.test', 'employee_id' => 'EMP-001'],
        ] as $duplicateIdentity) {
            try {
                User::factory()->create($duplicateIdentity);
                $this->fail('An existing global user identity constraint was removed.');
            } catch (QueryException) {
                $this->assertDatabaseCount('users', 1);
            }
        }
    }

    public function test_company_slug_is_unique_and_attendance_user_date_uniqueness_is_preserved(): void
    {
        Company::create(['name' => 'First Company', 'slug' => 'first-company']);

        try {
            Company::create(['name' => 'Duplicate Slug', 'slug' => 'first-company']);
            $this->fail('A duplicate company slug was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('companies', 1);
        }

        $user = User::factory()->create();
        Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-10-23',
            'check_in_time' => '08:00:00',
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);

        try {
            Attendance::create([
                'user_id' => $user->id,
                'attendance_date' => '2026-10-23',
                'check_in_time' => '09:00:00',
                'latitude' => 0,
                'longitude' => 0,
                'distance' => 0,
                'status' => 'present',
            ]);
            $this->fail('The existing per-user daily attendance constraint was removed.');
        } catch (QueryException) {
            $this->assertDatabaseCount('attendances', 1);
        }
    }
}
