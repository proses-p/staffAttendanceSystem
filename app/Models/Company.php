<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'slug',
        'ai_enabled',
        'payroll_mode',
    ];

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function officeLocations(): HasMany
    {
        return $this->hasMany(OfficeLocation::class);
    }

    public function attendanceSetting(): HasOne
    {
        return $this->hasOne(AttendanceSetting::class);
    }

    public function attendanceHolidays(): HasMany
    {
        return $this->hasMany(AttendanceHoliday::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function staffLeaveRecords(): HasMany
    {
        return $this->hasMany(StaffLeaveRecord::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function financialBudgets(): HasMany
    {
        return $this->hasMany(CompanyFinancialBudget::class);
    }

    public function compensationProfiles(): HasMany
    {
        return $this->hasMany(StaffCompensationProfile::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(MonthlyPayrollRecord::class);
    }
}
