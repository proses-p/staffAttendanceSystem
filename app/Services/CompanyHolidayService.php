<?php

namespace App\Services;

use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class CompanyHolidayService
{
    public function forDate(int|string $companyId, CarbonInterface|string $date): ?AttendanceHoliday
    {
        return AttendanceHoliday::query()
            ->where('company_id', $companyId)
            ->whereDate('holiday_date', Carbon::parse($date)->toDateString())
            ->first();
    }

    public function isHoliday(int|string $companyId, CarbonInterface|string $date): bool
    {
        return $this->forDate($companyId, $date) !== null;
    }

    public function datesInRange(
        int|string $companyId,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate
    ): Collection {
        return AttendanceHoliday::query()
            ->where('company_id', $companyId)
            ->whereDate('holiday_date', '>=', Carbon::parse($startDate)->toDateString())
            ->whereDate('holiday_date', '<=', Carbon::parse($endDate)->toDateString())
            ->get();
    }

    public function scheduledLeaveDays(User $user, CarbonInterface|string $startDate, CarbonInterface|string $endDate): ?int
    {
        $shift = $user->shift()
            ->where('company_id', $user->company_id)
            ->first();
        $workingDays = $shift?->working_days;

        if ($workingDays === null) {
            $workingDays = AttendanceSetting::query()
                ->where('company_id', $user->company_id)
                ->first()?->working_days;
        }

        $weekdayNumbers = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];
        $workingDayNumbers = [];
        foreach (is_array($workingDays) ? $workingDays : [] as $day) {
            $number = is_numeric($day)
                ? (int) $day
                : ($weekdayNumbers[strtolower((string) $day)] ?? null);
            if ($number !== null && $number >= 1 && $number <= 7) {
                $workingDayNumbers[] = $number;
            }
        }
        if ($workingDayNumbers === []) {
            return null;
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $holidays = $this->datesInRange($user->company_id, $start, $end)
            ->mapWithKeys(fn (AttendanceHoliday $holiday) => [
                $holiday->holiday_date->toDateString() => true,
            ]);

        $leaveDays = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (
                in_array($date->dayOfWeekIso, $workingDayNumbers, true) &&
                ! $holidays->has($date->toDateString())
            ) {
                $leaveDays++;
            }
        }

        return $leaveDays;
    }
}
