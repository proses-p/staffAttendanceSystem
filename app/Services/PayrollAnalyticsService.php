<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use App\Models\CompanyFinancialBudget;
use App\Models\Company;
use App\Models\DailyWorkSubmission;
use App\Models\MonthlyPayrollRecord;
use App\Models\SalaryAdvanceRequest;
use App\Models\StaffCompensationProfile;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class PayrollAnalyticsService
{
    public function calculateMonth(
        int $companyId,
        string $month,
        bool $persistDrafts = true,
        ?int $staffUserId = null
    ): array
    {
        $payrollMode = Company::query()
            ->whereKey($companyId)
            ->value('payroll_mode') ?? 'full_payroll';
        $allowancesOnly = $payrollMode === 'allowances_only';
        $periodStart = Carbon::createFromFormat('!Y-m', $month, config('app.timezone'));
        $periodEnd = $periodStart->copy()->endOfMonth();
        $today = Carbon::today(config('app.timezone'));
        $setting = AttendanceSetting::query()->where('company_id', $companyId)->first();
        $holidays = AttendanceHoliday::query()
            ->where('company_id', $companyId)
            ->whereBetween('holiday_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceHoliday $holiday): string => $holiday->holiday_date->toDateString());
        $staff = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->when($staffUserId !== null, fn ($query) => $query->whereKey($staffUserId))
            ->with('shift')
            ->orderBy('name')
            ->get();
        $profiles = StaffCompensationProfile::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->keyBy('user_id');
        $rows = [];

        foreach ($staff as $user) {
            $shift = $user->shift;
            if ($shift && (int) $shift->company_id !== $companyId) {
                $shift = null;
            }
            $workingDays = $shift?->working_days ?? $setting?->working_days;
            $workingDayNumbers = $this->workingDayNumbers($workingDays);
            $startTime = $shift?->start_time?->format('H:i')
                ?? $setting?->expected_arrival_time?->format('H:i');
            $endTime = $shift?->end_time?->format('H:i');
            $expectedMinutes = $shift
                ? $this->durationMinutes($startTime, $endTime)
                : ($setting?->expected_work_minutes ?? 480);
            if (! $shift && $startTime !== null) {
                $endTime = Carbon::parse('2000-01-01 '.$startTime)
                    ->addMinutes($expectedMinutes)
                    ->format('H:i');
            }
            $graceMinutes = $shift?->grace_period_minutes
                ?? $setting?->grace_period_minutes
                ?? 0;

            $attendances = Attendance::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->whereBetween('attendance_date', [
                    $periodStart->toDateString(),
                    $periodEnd->toDateString(),
                ])
                ->get()
                ->keyBy(fn (Attendance $attendance): string => $attendance->attendance_date->toDateString());
            $leaveDates = $this->approvedLeaveDates($companyId, (int) $user->id, $periodStart, $periodEnd);
            $acceptedReports = DailyWorkSubmission::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->whereBetween('submission_date', [
                    $periodStart->toDateString(),
                    $periodEnd->toDateString(),
                ])
                ->where('review_status', 'accepted')
                ->get();
            $acceptedDates = $acceptedReports->mapWithKeys(fn (DailyWorkSubmission $report): array => [
                $report->submission_date->toDateString() => true,
            ]);

            $workingDaysCount = 0;
            $onTimeDays = 0;
            $beforeTimeDays = 0;
            $lateDays = 0;
            $absentDays = 0;
            $attendedDays = 0;
            $incompleteHoursDays = 0;
            $missingReportDays = 0;
            $totalMinutes = 0;
            $overtimeMinutes = 0;

            foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
                $dateKey = $date->toDateString();
                $isHoliday = $holidays->has($dateKey);
                $isScheduledDay = in_array($date->dayOfWeekIso, $workingDayNumbers, true);
                if (! $isScheduledDay && ! $isHoliday) {
                    continue;
                }
                $attendance = $attendances->get($dateKey);
                $checkIn = $attendance?->check_in_at;
                $checkOut = $attendance?->check_out_at;

                if ($isHoliday) {
                    if ($checkIn !== null && $checkOut !== null && $checkOut->gte($checkIn)) {
                        $holidayMinutes = (int) floor($checkIn->diffInSeconds($checkOut) / 60);
                        $totalMinutes += $holidayMinutes;
                        $overtimeMinutes += $holidayMinutes;
                    }
                    continue;
                }
                if (! $isScheduledDay) {
                    continue;
                }
                $workingDaysCount++;
                if (isset($leaveDates[$dateKey])) {
                    continue;
                }
                if ($checkIn !== null) {
                    $attendedDays++;
                    $shiftStart = $startTime === null
                        ? null
                        : Carbon::parse($dateKey.' '.$startTime, config('app.timezone'));
                    if ($shiftStart !== null) {
                        if ($checkIn->lt($shiftStart)) {
                            $beforeTimeDays++;
                        }
                        if ($checkIn->lte($shiftStart->copy()->addMinutes($graceMinutes))) {
                            $onTimeDays++;
                        } else {
                            $lateDays++;
                        }
                    }

                    $workedMinutes = 0;
                    if ($checkOut !== null && $checkOut->gte($checkIn)) {
                        $workedMinutes = (int) floor($checkIn->diffInSeconds($checkOut) / 60);
                        $totalMinutes += $workedMinutes;
                    }
                    if ($workedMinutes < $expectedMinutes) {
                        $incompleteHoursDays++;
                    }
                    if (! $acceptedDates->has($dateKey)) {
                        $missingReportDays++;
                    }

                    if ($checkOut !== null && $checkOut->gte($checkIn)) {
                        if ($startTime !== null && $endTime !== null) {
                            $shiftStart = Carbon::parse(
                                $dateKey.' '.$startTime,
                                config('app.timezone')
                            );
                            $shiftEnd = Carbon::parse(
                                $dateKey.' '.$endTime,
                                config('app.timezone')
                            );
                            if ($shiftEnd->lte($shiftStart)) {
                                $shiftEnd->addDay();
                            }
                            $overtimeMinutes += max(
                                0,
                                (int) floor(($checkOut->getTimestamp() - $shiftEnd->getTimestamp()) / 60)
                            );
                        } else {
                            $overtimeMinutes += max(0, $workedMinutes - $expectedMinutes);
                        }
                    }
                } elseif ($checkIn === null && $checkOut === null && $date->lt($today)) {
                    $absentDays++;
                }
            }

            $onTimeRate = $workingDaysCount > 0
                ? round(($onTimeDays / $workingDaysCount) * 100, 2)
                : 0.0;
            $profile = $profiles->get($user->id);
            $basicSalary = $allowancesOnly ? 0.0 : (float) ($profile?->basic_salary ?? 0);
            $overtimeRate = (float) ($profile?->overtime_hourly_rate ?? 0);
            $beforeBonus = (float) ($profile?->before_time_bonus_per_day ?? 0);
            $reportBonus = (float) ($profile?->daily_work_bonus_per_report ?? 0);
            $deductAbsences = ! $allowancesOnly && ($profile?->deduct_unexcused_absences ?? true);
            $dailyRate = $basicSalary / max($workingDaysCount, 1);
            $absenceDeduction = $deductAbsences ? $absentDays * $dailyRate : 0;
            $overtimeHours = round($overtimeMinutes / 60, 2);
            $totalWorkingHours = round($totalMinutes / 60, 2);
            $overtimePay = $overtimeHours * $overtimeRate;
            $punctualityAllowance = $beforeTimeDays * $beforeBonus;
            $workReportAllowance = $acceptedReports->count() * $reportBonus;
            $salaryAdvanceDeduction = (float) SalaryAdvanceRequest::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('advance_month', $month)
                ->where('status', 'approved')
                ->sum('amount');
            $netPay = max(
                0,
                ($basicSalary - $absenceDeduction - $salaryAdvanceDeduction) +
                $overtimePay +
                $punctualityAllowance +
                $workReportAllowance
            );
            $overtimePay = round($overtimePay, 2);
            $punctualityAllowance = round($punctualityAllowance, 2);
            $workReportAllowance = round($workReportAllowance, 2);
            $absenceDeduction = round($absenceDeduction, 2);
            $salaryAdvanceDeduction = round($salaryAdvanceDeduction, 2);
            $netPay = round($netPay, 2);

            $riskFactors = [
                'absences' => $workingDaysCount > 0
                    ? ($absentDays / $workingDaysCount) * 100
                    : 0,
                'late_arrivals' => $workingDaysCount > 0 ? 100 - $onTimeRate : 0,
                'missing_reports' => $attendedDays > 0
                    ? ($missingReportDays / $attendedDays) * 100
                    : 0,
                'incomplete_hours' => $attendedDays > 0
                    ? ($incompleteHoursDays / $attendedDays) * 100
                    : 0,
            ];
            $riskScore = (int) round(
                $riskFactors['absences'] * 0.35 +
                $riskFactors['late_arrivals'] * 0.25 +
                $riskFactors['missing_reports'] * 0.25 +
                $riskFactors['incomplete_hours'] * 0.15
            );
            $values = [
                'working_days_count' => $workingDaysCount,
                'on_time_days' => $onTimeDays,
                'before_time_days' => $beforeTimeDays,
                'late_days' => $lateDays,
                'absent_days' => $absentDays,
                'on_time_rate' => $onTimeRate,
                'total_working_hours' => $totalWorkingHours,
                'overtime_hours' => $overtimeHours,
                'accepted_work_reports' => $acceptedReports->count(),
                'basic_salary' => $basicSalary,
                'overtime_pay' => $overtimePay,
                'punctuality_allowance' => $punctualityAllowance,
                'work_report_allowance' => $workReportAllowance,
                'absence_deduction' => $absenceDeduction,
                'salary_advance_deduction' => $salaryAdvanceDeduction,
                'net_pay' => $netPay,
                'risk_score' => min(100, max(0, $riskScore)),
            ];

            $record = MonthlyPayrollRecord::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('payroll_month', $month)
                ->first();
            if ($record?->status === 'approved') {
                $values = $record->only(array_keys($values));
                if ($allowancesOnly) {
                    $values['basic_salary'] = 0;
                    $values['absence_deduction'] = 0;
                    $values['net_pay'] = round(max(
                        0,
                        (float) $values['overtime_pay'] +
                        (float) $values['punctuality_allowance'] +
                        (float) $values['work_report_allowance'] -
                        (float) $values['salary_advance_deduction']
                    ), 2);
                }
            } elseif ($persistDrafts) {
                $record = MonthlyPayrollRecord::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                        'payroll_month' => $month,
                    ],
                    $values + ['status' => 'draft']
                );
            }

            $rows[] = [
                'user' => $user,
                'status' => $record?->status ?? 'draft',
                'risk_category' => $this->riskCategory((int) $values['risk_score']),
                'risk_factors' => $riskFactors,
                'record' => $record,
                ...$values,
                'attended_days' => $attendedDays,
                'accepted_attended_reports' => max(0, $attendedDays - $missingReportDays),
                'allowances' => (float) $values['punctuality_allowance'] +
                    (float) $values['work_report_allowance'],
                'total_allowances' => (float) $values['overtime_pay'] +
                    (float) $values['punctuality_allowance'] +
                    (float) $values['work_report_allowance'],
            ];
        }

        if ($staffUserId !== null) {
            return ['month' => $month, 'rows' => $rows];
        }

        return $this->summary($companyId, $month, $rows);
    }

    public function approveMonth(int $companyId, string $month, int $approverId): void
    {
        $report = $this->calculateMonth($companyId, $month, false);
        DB::transaction(function () use ($companyId, $month, $approverId, $report): void {
            foreach ($report['rows'] as $row) {
                $record = MonthlyPayrollRecord::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $row['user']->id)
                    ->where('payroll_month', $month)
                    ->lockForUpdate()
                    ->first();
                if ($record?->status === 'approved') {
                    continue;
                }
                MonthlyPayrollRecord::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'user_id' => $row['user']->id,
                        'payroll_month' => $month,
                    ],
                    collect($row)->only([
                        'working_days_count',
                        'on_time_days',
                        'before_time_days',
                        'late_days',
                        'absent_days',
                        'on_time_rate',
                        'total_working_hours',
                        'overtime_hours',
                        'accepted_work_reports',
                        'basic_salary',
                        'overtime_pay',
                        'punctuality_allowance',
                        'work_report_allowance',
                        'absence_deduction',
                        'salary_advance_deduction',
                        'net_pay',
                        'risk_score',
                    ])->all() + [
                        'status' => 'approved',
                        'approved_by' => $approverId,
                    ]
                );
            }
        });
    }

    private function summary(int $companyId, string $month, array $rows): array
    {
        $budget = CompanyFinancialBudget::query()
            ->where('company_id', $companyId)
            ->where('budget_month', $month)
            ->first();
        $allocatedBudget = (float) ($budget?->allocated_budget ?? 0);
        $totalPayout = array_sum(array_column($rows, 'net_pay'));
        $allowances = array_sum(array_column($rows, 'total_allowances'));
        $factorTotals = [
            'absences' => 0.0,
            'late_arrivals' => 0.0,
            'missing_reports' => 0.0,
            'incomplete_hours' => 0.0,
        ];
        foreach ($rows as $row) {
            foreach ($factorTotals as $key => $_) {
                $factorTotals[$key] += $row['risk_factors'][$key];
            }
        }
        if ($rows !== []) {
            foreach ($factorTotals as $key => $value) {
                $factorTotals[$key] = round($value / count($rows), 2);
            }
        }
        $companyRisk = $rows === []
            ? 0
            : (int) round(array_sum(array_column($rows, 'risk_score')) / count($rows));
        $workingDays = array_sum(array_column($rows, 'working_days_count'));
        $onTimeDays = array_sum(array_column($rows, 'on_time_days'));
        $acceptedReports = array_sum(array_column($rows, 'accepted_work_reports'));
        $attendedDays = array_sum(array_column($rows, 'attended_days'));
        $acceptedAttendedReports = array_sum(array_column($rows, 'accepted_attended_reports'));

        return [
            'month' => $month,
            'budget' => $budget,
            'allocated_budget' => $allocatedBudget,
            'total_payout' => $totalPayout,
            'budget_variance' => $allocatedBudget - $totalPayout,
            'total_allowances' => $allowances,
            'company_risk_score' => min(100, max(0, $companyRisk)),
            'company_risk_category' => $this->riskCategory($companyRisk),
            'risk_factors' => $factorTotals,
            'on_time_rate' => $workingDays > 0 ? round($onTimeDays / $workingDays * 100, 2) : 0,
            'work_report_completion_rate' => $attendedDays > 0
                ? round($acceptedAttendedReports / $attendedDays * 100, 2)
                : 0,
            'rows' => $rows,
        ];
    }

    private function approvedLeaveDates(
        int $companyId,
        int $userId,
        Carbon $start,
        Carbon $end
    ): array {
        $leaves = StaffLeaveRecord::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);
        $dates = [];
        foreach ($leaves as $leave) {
            $rangeStart = $leave->start_date->copy()->max($start);
            $rangeEnd = $leave->end_date->copy()->min($end);
            foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                $dates[$date->toDateString()] = true;
            }
        }

        return $dates;
    }

    private function workingDayNumbers(?array $days): array
    {
        if ($days === null || $days === []) {
            return [1, 2, 3, 4, 5];
        }
        $map = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];
        $numbers = [];
        foreach ($days as $day) {
            $number = is_numeric($day) ? (int) $day : ($map[strtolower((string) $day)] ?? null);
            if ($number !== null && $number >= 1 && $number <= 7) {
                $numbers[] = $number;
            }
        }

        return array_values(array_unique($numbers));
    }

    private function durationMinutes(?string $startTime, ?string $endTime): int
    {
        if ($startTime === null || $endTime === null) {
            return 480;
        }
        $start = Carbon::parse('2000-01-01 '.$startTime);
        $end = Carbon::parse('2000-01-01 '.$endTime);
        if ($end->lte($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }


    private function riskCategory(int $score): string
    {
        return match (true) {
            $score <= 25 => 'Low',
            $score <= 55 => 'Moderate',
            default => 'High',
        };
    }
}
