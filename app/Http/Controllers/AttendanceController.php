<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use App\Models\OfficeLocation;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use App\Services\StaffDeviceProofService;
use App\Services\CompanyHolidayService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:30'],
            'challenge_id' => ['required', 'integer', 'min:1'],
            'device_id' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'max:512'],
            'location_timestamp' => ['required', 'string', 'size:24'],
        ]);
        app(StaffDeviceProofService::class)->verifyAttendance(
            $request->user(),
            $data,
            'check-in'
        );

        $latitude = $data['latitude'];
        $longitude = $data['longitude'];
        $accuracy = $data['accuracy'];
        $user = $request->user();
        [$office, $distance] = $this->officeForCoordinates(
            $user->company_id,
            $latitude,
            $longitude
        );

        if (! $office) {
            return response()->json([
                'success' => false,
                'message' => 'No office location has been configured for your company.',
            ], 422);
        }

        $today = Carbon::today();
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'success' => false,
                'message' => 'You have already signed in today.',
            ], 409);
        }

        if ($distance > $office->allowed_radius) {
            return response()->json([
                'success' => false,
                'message' => 'You are outside the allowed office area.',
                'distance' => round($distance, 2),
            ], 403);
        }

        try {
            $attendance = new Attendance([
                'user_id' => $user->id,
                'attendance_date' => $today->toDateString(),
                'check_in_time' => now()->format('H:i:s'),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'distance' => $distance,
                'sign_in_location' => $this->reverseGeocode($latitude, $longitude, $office->office_name),
                'status' => 'present',
            ]);
            $attendance->company_id = $user->company_id;
            $attendance->save();
        } catch (QueryException $exception) {
            if (Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', $today)
                ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already signed in today.',
                ], 409);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data' => [
                'date' => $attendance->attendance_date->format('d F Y'),
                'day' => $attendance->attendance_date->format('l'),
                'attendance_state' => 'in_progress',
                'check_in_at' => $attendance->check_in_at->toIso8601String(),
                'check_out_at' => null,
                'check_in_time' => $attendance->check_in_at->format('h:i A'),
                'check_out_time' => null,
                'arrival_time' => Carbon::parse(
                    $attendance->check_in_time
                )->format('h:i A'),
                'work_duration' => $attendance->work_duration,
                'status' => $attendance->status,
                'location' => $attendance->sign_in_location,
            ],
        ], 201);
    }

    public function checkOut(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:30'],
            'challenge_id' => ['required', 'integer', 'min:1'],
            'device_id' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'max:512'],
            'location_timestamp' => ['required', 'string', 'size:24'],
        ]);
        app(StaffDeviceProofService::class)->verifyAttendance(
            $request->user(),
            $data,
            'check-out'
        );
        $latitude = $data['latitude'];
        $longitude = $data['longitude'];
        $user = $request->user();
        [$office, $distance] = $this->officeForCoordinates(
            $user->company_id,
            $latitude,
            $longitude
        );

        if (! $office) {
            return response()->json([
                'success' => false,
                'message' => 'No office location has been configured for your company.',
            ], 422);
        }

        $today = Carbon::today();

        if ($distance > $office->allowed_radius) {
            return response()->json([
                'success' => false,
                'message' => 'You are outside the allowed office area.',
                'distance' => round($distance, 2),
            ], 403);
        }

        $signOutLocation = $this->reverseGeocode(
            $latitude,
            $longitude,
            $office->office_name
        );
        $result = DB::transaction(function () use (
            $today,
            $latitude,
            $longitude,
            $distance,
            $signOutLocation,
            $user
        ) {
            $attendance = Attendance::where('user_id', $user->id)
                ->where('company_id', $user->company_id)
                ->whereDate('attendance_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                return ['error' => [
                    'status' => 404,
                    'message' => 'You have not checked in today.',
                ]];
            }

            if ((int) $attendance->company_id !== (int) $user->company_id) {
                return ['error' => [
                    'status' => 409,
                    'message' => 'The existing attendance record has no verified company assignment. Contact your administrator.',
                ]];
            }

            if ($attendance->check_out_time) {
                return ['error' => [
                    'status' => 409,
                    'message' => 'You have already checked out today.',
                ]];
            }

            $attendance->update([
                'check_out_time' => now()->format('H:i:s'),
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'check_out_distance' => $distance,
                'sign_out_location' => $signOutLocation,
            ]);

            return ['attendance' => $attendance->refresh()];
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
            ], $result['error']['status']);
        }

        $attendance = $result['attendance'];

        return response()->json([
            'success' => true,
            'message' => 'You have successfully signed out.',
            'data' => [
                'date' => $attendance->attendance_date->format('d F Y'),
                'day' => $attendance->attendance_date->format('l'),
                'attendance_state' => 'completed',
                'check_in_at' => $attendance->check_in_at->toIso8601String(),
                'check_out_at' => $attendance->check_out_at->toIso8601String(),
                'check_in_time' => $attendance->check_in_at->format('h:i A'),
                'check_out_time' => $attendance->check_out_at->format('h:i A'),
                'departure_time' => Carbon::parse(
                    $attendance->check_out_time
                )->format('h:i A'),
                'work_duration' => $attendance->work_duration,
                'status' => $attendance->status,
                'location' => $attendance->sign_out_location,
            ],
        ]);
    }

    // storing temporarily the location controllerer
    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);
        $userId = auth()->id();
        Cache::put(
            "staff_location:{$userId}",
            [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'updated_at' => now()->toDateTimeString(),

            ],
            now()->addMinutes(5)
        );

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully.',
        ]);
    }

    public function history(Request $request)
    {
        $attendances = $request->user()
            ->attendances()
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate(6);

        return response()->json([
            'success' => true,
            'data' => [
                'records' => $attendances->getCollection()
                    ->map(fn (Attendance $attendance) => [
                        'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
                        'check_in_at' => $attendance->check_in_at?->toIso8601String(),
                        'check_out_at' => $attendance->check_out_at?->toIso8601String(),
                        'check_in_time' => $attendance->check_in_at?->format('h:i A'),
                        'check_out_time' => $attendance->check_out_at?->format('h:i A'),
                        'sign_in_location' => $attendance->sign_in_location,
                        'sign_out_location' => $attendance->sign_out_location,
                    ])
                    ->values(),
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }

    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $latDifference = deg2rad($latitude2 - $latitude1);
        $lonDifference = deg2rad($longitude2 - $longitude1);

        $a =
            sin($latDifference / 2) ** 2 +
            cos(deg2rad($latitude1)) *
            cos(deg2rad($latitude2)) *
            sin($lonDifference / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }

    private function officeForCoordinates(
        int $companyId,
        float $latitude,
        float $longitude
    ): array {
        $offices = OfficeLocation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (OfficeLocation $office): bool => (float) $office->latitude >= -90 &&
                    (float) $office->latitude <= 90 &&
                    (float) $office->longitude >= -180 &&
                    (float) $office->longitude <= 180 &&
                    $office->allowed_radius > 0
            )
            ->map(fn (OfficeLocation $office): array => [
                'office' => $office,
                'distance' => $this->calculateDistance(
                    $latitude,
                    $longitude,
                    (float) $office->latitude,
                    (float) $office->longitude
                ),
            ])
            ->sortBy('distance')
            ->values();

        if ($offices->isEmpty()) {
            return [null, null];
        }

        $match = $offices->first(
            fn (array $candidate): bool => $candidate['distance'] <= $candidate['office']->allowed_radius
        ) ?? $offices->first();

        return [$match['office'], $match['distance']];
    }

    private function reverseGeocode(float $latitude, float $longitude, ?string $fallback = null): string
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => 'StaffFlow Attendance/1.0'])
                ->timeout(3)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'zoom' => 18,
                ]);

            if ($response->successful() && $response->json('display_name')) {
                return $response->json('display_name');
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $fallback ?: 'Location unavailable';
    }

    // get dashboard data
    public function getDashboardData(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));
        $holiday = app(CompanyHolidayService::class)->forDate($user->company_id, $today);
        $schedule = $this->dashboardSchedule($user, $today);
        $schedule['is_holiday_today'] = $holiday !== null;
        $schedule['holiday_name'] = $holiday?->name;
        $statistics = $this->dashboardStatistics($user, $today, $schedule);
        $scheduleResponse = array_intersect_key($schedule, array_flip([
            'shift_name',
            'start_time',
            'end_time',
            'working_days',
            'is_working_day_today',
            'has_work_schedule',
            'is_holiday_today',
            'holiday_name',
        ]));
        $office = OfficeLocation::query()
            ->where('company_id', $user->company_id)
            ->orderBy('id')
            ->first();
        $officeResponse = [
            'office_latitude' => $office?->latitude === null
                ? null
                : (float) $office->latitude,
            'office_longitude' => $office?->longitude === null
                ? null
                : (float) $office->longitude,
            'geofence_radius_meters' => $office?->allowed_radius === null
                ? null
                : (float) $office->allowed_radius,
            'office_name' => $office?->office_name,
        ];

        // check if staff has sign in today
        $attendance = Attendance::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereDate('attendance_date', $today)
            ->first();

        // if not sign we return default values
        if (! $attendance) {
            return response()->json([
                'user_name' => $user->name,
                'current_time' => now()->format('h:i A'),
                'current_date' => now()->format('l, F d'),
                'attendance_date' => $today->format('Y-m-d'),
                'attendance_state' => 'not_started',
                'worked_hours' => '--',
                'check_in_time' => '--:-- --',
                'check_out_time' => '--:-- --',
                'is_checked_in' => false,
                'statistics' => $statistics,
                ...$scheduleResponse,
                ...$officeResponse,
            ]);
        }

        // if is already sign in we return the data from the database
        $checkInAt = $attendance->check_in_at;
        $checkOutAt = $attendance->check_out_at;

        return response()->json([
            'user_name' => $user->name,
            'current_time' => now()->format('h:i A'),
            'current_date' => now()->format('l, F d'),
            'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
            'attendance_state' => $checkOutAt ? 'completed' : 'in_progress',
            'worked_hours' => $attendance->work_duration ?? '--',
            'check_in_at' => $checkInAt?->toIso8601String(),
            'check_out_at' => $checkOutAt?->toIso8601String(),
            'check_in_time' => $checkInAt?->format('h:i A') ?? '--:-- --',
            'check_out_time' => $checkOutAt?->format('h:i A') ?? '--:-- --',
            'is_checked_in' => $checkInAt !== null && $checkOutAt === null,
            'statistics' => $statistics,
            ...$scheduleResponse,
            ...$officeResponse,
        ]);
    }

    private function dashboardSchedule(User $user, Carbon $today): array
    {
        $shift = $user->shift()
            ->where('company_id', $user->company_id)
            ->first();
        $setting = AttendanceSetting::where('company_id', $user->company_id)->first();
        $weekdayNumbers = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];
        $configuredWorkingDays = $shift?->working_days ?? $setting?->working_days ?? [];
        $workingDayNumbers = [];

        foreach (is_array($configuredWorkingDays) ? $configuredWorkingDays : [] as $day) {
            $number = is_numeric($day)
                ? (int) $day
                : ($weekdayNumbers[strtolower((string) $day)] ?? null);
            if ($number !== null && $number >= 1 && $number <= 7) {
                $workingDayNumbers[] = $number;
            }
        }
        $workingDayNumbers = array_values(array_unique($workingDayNumbers));

        $startTime = $shift?->start_time?->format('H:i')
            ?? $setting?->expected_arrival_time?->format('H:i');
        $expectedWorkMinutes = $shift
            ? $this->shiftDurationMinutes($shift->start_time->format('H:i'), $shift->end_time->format('H:i'))
            : $setting?->expected_work_minutes;
        $endTime = $shift?->end_time?->format('H:i');
        if (! $shift && $startTime !== null && $expectedWorkMinutes !== null) {
            $endTime = Carbon::parse('2000-01-01 '.$startTime)
                ->addMinutes($expectedWorkMinutes)
                ->format('H:i');
        }

        return [
            'shift_name' => $shift?->name,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'working_days' => array_map(
                fn (int $number) => array_search($number, $weekdayNumbers, true),
                $workingDayNumbers
            ),
            'is_working_day_today' => $workingDayNumbers === []
                || in_array($today->dayOfWeekIso, $workingDayNumbers, true),
            'has_work_schedule' => $workingDayNumbers !== [],
            '_working_day_numbers' => $workingDayNumbers,
            '_expected_work_minutes' => $expectedWorkMinutes,
            '_grace_period_minutes' => $shift?->grace_period_minutes ?? $setting?->grace_period_minutes,
            '_work_schedule_configured' => $workingDayNumbers !== [],
        ];
    }

    private function shiftDurationMinutes(string $startTime, string $endTime): int
    {
        $start = Carbon::parse('2000-01-01 '.$startTime);
        $end = Carbon::parse('2000-01-01 '.$endTime);
        if ($end->lte($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    private function dashboardStatistics(
        User $user,
        Carbon $today,
        array $schedule
    ): array {
        $periodStart = $today->copy()->startOfMonth();
        $records = $user->attendances()
            ->where('company_id', $user->company_id)
            ->whereDate('attendance_date', '>=', $periodStart->toDateString())
            ->whereDate('attendance_date', '<=', $today->toDateString())
            ->orderBy('attendance_date')
            ->orderBy('id')
            ->get();

        $dailyRecords = $records->groupBy(
            fn (Attendance $attendance) => $attendance->attendance_date->toDateString()
        )->map(fn ($dayRecords) => $dayRecords->first());

        $holidays = app(CompanyHolidayService::class)
            ->datesInRange($user->company_id, $periodStart, $today)
            ->mapWithKeys(fn (AttendanceHoliday $holiday) => [
                $holiday->holiday_date->toDateString() => true,
            ]);

        $approvedLeaveRecords = StaffLeaveRecord::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get();

        $workingDays = $schedule['_working_day_numbers'];
        $expectedWorkMinutes = $schedule['_expected_work_minutes'];
        $arrivalTimeText = $schedule['start_time'];
        $endTimeText = $schedule['end_time'];
        $gracePeriodMinutes = $schedule['_grace_period_minutes'];
        $workScheduleConfigured = $schedule['_work_schedule_configured'];
        $hoursConfigured = $expectedWorkMinutes !== null && $expectedWorkMinutes > 0;
        $arrivalConfigured = $arrivalTimeText !== null;

        $approvedLeaveDates = [];
        if ($workScheduleConfigured) {
            foreach ($approvedLeaveRecords as $leaveRecord) {
                $leaveStart = $leaveRecord->start_date->max($periodStart);
                $leaveEnd = $leaveRecord->end_date->min($today);
                for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                    $dateKey = $date->toDateString();
                    if (
                        in_array($date->dayOfWeekIso, $workingDays, true) &&
                        ! $holidays->has($dateKey)
                    ) {
                        $approvedLeaveDates[$dateKey] = true;
                    }
                }
            }
        }

        $workedMinutes = 0;
        $overtimeMinutes = 0;
        $standardWorkedMinutes = 0;
        $beforeTimeMinutes = 0;
        $eligibleScheduledDays = 0;
        $attendedDays = 0;
        $onTimeDays = 0;
        $lateDays = 0;
        $overtimeDays = 0;
        $incompleteDays = 0;
        $absentDays = 0;
        $holidayOvertimeDays = 0;
        $now = Carbon::now(config('app.timezone'));
        for ($date = $periodStart->copy(); $date->lte($today); $date->addDay()) {
            $dateKey = $date->toDateString();
            $attendance = $dailyRecords->get($dateKey);
            $checkInAt = $attendance?->check_in_at;
            $checkOutAt = $attendance?->check_out_at;
            $durationMinutes = null;

            if ($checkInAt && $checkOutAt && $checkOutAt->gte($checkInAt)) {
                $durationMinutes = (int) $checkInAt->diffInMinutes($checkOutAt);
                $workedMinutes += $durationMinutes;
            }

            if ($holidays->has($dateKey)) {
                if ($durationMinutes !== null && $checkInAt && $checkOutAt) {
                    $overtimeMinutes += $durationMinutes;
                    $overtimeDays++;
                    $holidayOvertimeDays++;
                }
                continue;
            }

            if (
                ! $workScheduleConfigured ||
                ! in_array($date->dayOfWeekIso, $workingDays, true) ||
                isset($approvedLeaveDates[$dateKey])
            ) {
                continue;
            }

            if ($date->isSameDay($today) && $arrivalConfigured && ! $checkInAt) {
                $arrivalDeadline = Carbon::parse(
                    $dateKey.' '.$arrivalTimeText,
                    config('app.timezone')
                )->addMinutes($gracePeriodMinutes ?? 0);
                if ($now->lt($arrivalDeadline)) {
                    continue;
                }
            }

            $eligibleScheduledDays++;
            if ($checkInAt) {
                $attendedDays++;
                $shiftStart = $arrivalConfigured
                    ? Carbon::parse(
                        $dateKey.' '.$arrivalTimeText,
                        config('app.timezone')
                    )
                    : null;
                if ($arrivalConfigured) {
                    $arrivalDeadline = $shiftStart->copy()->addMinutes(
                        $gracePeriodMinutes ?? 0
                    );
                    if ($checkInAt->lt($shiftStart)) {
                        $beforeTimeMinutes += max(
                            0,
                            (int) floor(
                                ($shiftStart->getTimestamp() - $checkInAt->getTimestamp()) / 60
                            )
                        );
                    }
                    if ($checkInAt->lte($arrivalDeadline)) {
                        $onTimeDays++;
                    } else {
                        $lateDays++;
                    }
                }

                if ($hoursConfigured) {
                    if ($durationMinutes === null) {
                        if (! $date->isSameDay($today)) {
                            $incompleteDays++;
                        }
                    } else {
                        if ($shiftStart !== null) {
                            $shiftEnd = Carbon::parse(
                                $dateKey.' '.$endTimeText,
                                config('app.timezone')
                            );
                            if ($shiftEnd->lte($shiftStart)) {
                                $shiftEnd->addDay();
                            }
                            $overtimeForDay = max(
                                0,
                                (int) floor(
                                    ($checkOutAt->getTimestamp() - $shiftEnd->getTimestamp()) / 60
                                )
                            );
                            $overtimeMinutes += $overtimeForDay;
                            if ($overtimeForDay > 0) {
                                $overtimeDays++;
                            }
                            $standardWorkedMinutes += max(
                                0,
                                (int) floor(
                                    (
                                        min($checkOutAt->getTimestamp(), $shiftEnd->getTimestamp()) -
                                        max($checkInAt->getTimestamp(), $shiftStart->getTimestamp())
                                    ) / 60
                                )
                            );
                        }
                        if ($durationMinutes < $expectedWorkMinutes) {
                            $incompleteDays++;
                        }
                    }
                }
            } else {
                if ($date->lt($today) && $attendance === null) {
                    $absentDays++;
                }
            }
        }

        $policyNeedsWorkdays = ! $workScheduleConfigured
            ? 'Working days are not configured.'
            : null;
        $policyNeedsHours = ! $hoursConfigured
            ? 'Expected daily working hours are not configured.'
            : null;
        $policyNeedsArrival = ! $arrivalConfigured
            ? 'Official work start time is not configured.'
            : null;
        $noEligibleDays = $eligibleScheduledDays === 0
            ? 'There are no eligible scheduled working days in this reporting period.'
            : null;

        $unavailableReasons = [
            'overtime' => $policyNeedsArrival ?? $policyNeedsHours ?? $policyNeedsWorkdays,
            'on_time_rate' => $policyNeedsArrival ?? $policyNeedsWorkdays ?? $noEligibleDays,
            'on_time_days' => $policyNeedsArrival ?? $policyNeedsWorkdays,
            'standard_worked_minutes' => $policyNeedsArrival ?? $policyNeedsHours ?? $policyNeedsWorkdays,
            'late_days' => $policyNeedsArrival ?? $policyNeedsWorkdays,
            'overtime_days' => $policyNeedsArrival ?? $policyNeedsHours ?? $policyNeedsWorkdays,
            'before_time_minutes' => $policyNeedsArrival ?? $policyNeedsWorkdays,
            'attendance_rate' => $policyNeedsWorkdays ?? $noEligibleDays,
            'approved_leave_days' => $policyNeedsWorkdays,
            'incomplete_days' => $policyNeedsHours ?? $policyNeedsWorkdays,
        ];

        return [
            'reporting_period' => [
                'start_date' => $periodStart->toDateString(),
                'end_date' => $today->toDateString(),
                'timezone' => config('app.timezone'),
                'label' => $periodStart->format('M j').' - '.$today->format('M j, Y'),
            ],
            'overtime_minutes' => ($arrivalConfigured && $hoursConfigured && $workScheduleConfigured) ||
                $holidayOvertimeDays > 0
                ? $overtimeMinutes
                : null,
            'overtime_days' => ($arrivalConfigured && $hoursConfigured && $workScheduleConfigured) ||
                $holidayOvertimeDays > 0
                ? $overtimeDays
                : null,
            'before_time_minutes' => $arrivalConfigured && $workScheduleConfigured
                ? $beforeTimeMinutes
                : null,
            'on_time_rate_percent' => $arrivalConfigured && $workScheduleConfigured && $eligibleScheduledDays > 0
                ? round(($onTimeDays / $eligibleScheduledDays) * 100, 1)
                : null,
            'on_time_days' => $arrivalConfigured && $workScheduleConfigured
                ? $onTimeDays
                : null,
            'standard_worked_minutes' => $arrivalConfigured && $hoursConfigured && $workScheduleConfigured
                ? $standardWorkedMinutes
                : null,
            'late_days' => $arrivalConfigured && $workScheduleConfigured
                ? $lateDays
                : null,
            'total_worked_minutes' => $workedMinutes,
            'attendance_rate_percent' => $workScheduleConfigured && $eligibleScheduledDays > 0
                ? round(($attendedDays / $eligibleScheduledDays) * 100, 1)
                : null,
            'approved_leave_days' => $workScheduleConfigured
                ? count($approvedLeaveDates)
                : null,
            'incomplete_days' => $hoursConfigured && $workScheduleConfigured
                ? $incompleteDays
                : null,
            'absent_days' => $workScheduleConfigured && $eligibleScheduledDays > 0
                ? $absentDays
                : null,
            'eligible_scheduled_days' => $workScheduleConfigured
                ? $eligibleScheduledDays
                : null,
            'has_attendance_history' => $records->isNotEmpty(),
            'unavailable_reasons' => array_filter($unavailableReasons),
        ];
    }
}
