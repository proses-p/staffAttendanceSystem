<?php

namespace App\View\Components;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\View\Component;
use Illuminate\View\View;

class AdminStatistics extends Component
{
    public int $totalStaff;
    public int $onTime;
    public int $late;
    public int $notSignedIn;

    public bool $isConfigured;
    public ?string $expectedArrivalTimeFormatted;

    public string $onTimePercent;
    public string $latePercent;
    public string $notSignedInPercent;

    public function __construct()
    {
        // Total staff
        $this->totalStaff = User::where('role', 'staff')->count();

        // Today's attendance
        $todayAttendances = Attendance::whereDate(
            'attendance_date',
            Carbon::today()
        )->get();

        // Expected arrival time
        $setting = AttendanceSetting::first();

        $this->isConfigured = $setting &&
            !empty($setting->expected_arrival_time);

        $this->expectedArrivalTimeFormatted = null;

        $this->onTime = 0;
        $this->late = 0;

        /*
        |--------------------------------------------------------------------------
        | On Time / Late
        |--------------------------------------------------------------------------
        */

        if ($this->isConfigured) {

            $expectedTime = Carbon::parse(
                $setting->expected_arrival_time
            );

            $this->expectedArrivalTimeFormatted =
                $expectedTime->format('h:i A');

            foreach ($todayAttendances as $attendance) {

                if (!$attendance->check_in_time) {
                    continue;
                }

                $checkInTime = Carbon::parse(
                    $attendance->check_in_time
                );

                if ($checkInTime->lte($expectedTime)) {
                    $this->onTime++;
                } else {
                    $this->late++;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Not Signed In
        |--------------------------------------------------------------------------
        */

        $signedInStaffIds = $todayAttendances
            ->pluck('user_id')
            ->unique();

        $this->notSignedIn = User::where('role', 'staff')
            ->whereNotIn('id', $signedInStaffIds)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Percentages
        |--------------------------------------------------------------------------
        */

        if ($this->totalStaff > 0) {

            $this->onTimePercent = number_format(
                ($this->onTime / $this->totalStaff) * 100,
                1
            );

            $this->latePercent = number_format(
                ($this->late / $this->totalStaff) * 100,
                1
            );

            $this->notSignedInPercent = number_format(
                ($this->notSignedIn / $this->totalStaff) * 100,
                1
            );

        } else {

            $this->onTimePercent = '0';
            $this->latePercent = '0';
            $this->notSignedInPercent = '0';
        }
    }

    public function render(): View
    {
        return view('components.admin-statistics');
    }
}
