<?php

namespace App\View\Components;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class AdminStatistics extends Component
{
    public int $totalStaff;
    public int $eligibleStaff;
    public int $onTime;
    public int $late;
    public int $notSignedIn;

    public bool $isConfigured;
    public ?string $expectedArrivalTimeFormatted;

    public string $onTimePercent;
    public string $latePercent;
    public string $notSignedInPercent;

    public function __construct(
        public Carbon $today,
        public Collection $attendanceTrend,
    )
    {
        $companyId = auth()->user()?->company_id;

        $staff = User::where('company_id', $companyId)
            ->where('role', 'staff')
            ->with('shift')
            ->get();
        $this->totalStaff = $staff->count();

        $setting = AttendanceSetting::where('company_id', $companyId)->first();
        $todayDate = Carbon::today(config('app.timezone'));
        $todayAttendances = Attendance::query()
            ->whereDate('attendance_date', $todayDate)
            ->where('company_id', $companyId)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->keyBy('user_id');
        $weekdayNumbers = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];
        $this->eligibleStaff = 0;
        $arrivalTimes = [];
        $this->expectedArrivalTimeFormatted = null;
        $this->onTime = 0;
        $this->late = 0;
        $this->notSignedIn = 0;

        foreach ($staff as $member) {
            $configuredDays = $member->shift?->working_days ?? $setting?->working_days ?? [];
            $workingDays = [];
            foreach (is_array($configuredDays) ? $configuredDays : [] as $day) {
                $number = is_numeric($day)
                    ? (int) $day
                    : ($weekdayNumbers[strtolower((string) $day)] ?? null);
                if ($number !== null && $number >= 1 && $number <= 7) {
                    $workingDays[] = $number;
                }
            }
            if ($workingDays !== [] && ! in_array($todayDate->dayOfWeekIso, $workingDays, true)) {
                continue;
            }

            $arrivalTime = $member->shift?->start_time?->format('H:i')
                ?? $setting?->expected_arrival_time?->format('H:i');
            if ($arrivalTime === null) {
                continue;
            }
            $this->eligibleStaff++;
            $arrivalTimes[] = $arrivalTime;
            $arrivalDeadline = Carbon::parse(
                $todayDate->toDateString().' '.$arrivalTime,
                config('app.timezone')
            )->addMinutes($member->shift?->grace_period_minutes ?? $setting?->grace_period_minutes ?? 0);
            $attendance = $todayAttendances->get($member->id);

            if ($attendance?->check_in_at) {
                if ($attendance->check_in_at->lte($arrivalDeadline)) {
                    $this->onTime++;
                } else {
                    $this->late++;
                }
            } elseif (Carbon::now(config('app.timezone'))->gte($arrivalDeadline)) {
                $this->notSignedIn++;
            }
        }

        $this->isConfigured = $arrivalTimes !== [];
        $uniqueArrivalTimes = array_values(array_unique($arrivalTimes));
        $this->expectedArrivalTimeFormatted = count($uniqueArrivalTimes) > 1
            ? 'Varies by assigned shift'
            : ($uniqueArrivalTimes !== []
                ? Carbon::parse($uniqueArrivalTimes[0])->format('h:i A')
                : null);

        $denominator = $this->eligibleStaff;
        $this->onTimePercent = $denominator > 0
            ? number_format(($this->onTime / $denominator) * 100, 1)
            : '0';
        $this->latePercent = $denominator > 0
            ? number_format(($this->late / $denominator) * 100, 1)
            : '0';
        $this->notSignedInPercent = $denominator > 0
            ? number_format(($this->notSignedIn / $denominator) * 100, 1)
            : '0';
    }

    public function render(): View
    {
        return view('components.admin-statistics');
    }
}
