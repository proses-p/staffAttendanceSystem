<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyFinancialBudget;
use App\Models\Company;
use App\Models\MonthlyPayrollRecord;
use App\Models\SalaryAdvanceRequest;
use App\Models\StaffNotification;
use App\Models\StaffCompensationProfile;
use App\Models\User;
use App\Services\PayrollAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancePayrollController extends Controller
{
    public function index(Request $request, PayrollAnalyticsService $analytics)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');
        $companyId = (int) $request->user()->company_id;
        $payrollMode = Company::query()->whereKey($companyId)->value('payroll_mode') ?? 'full_payroll';
        $report = $analytics->calculateMonth($companyId, $month);
        $advanceRequests = SalaryAdvanceRequest::query()
            ->with('user:id,name')
            ->where('company_id', $companyId)
            ->where('advance_month', $month)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->get();
        $staff = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $profiles = StaffCompensationProfile::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->keyBy('user_id');
        $allApproved = $report['rows'] !== [] &&
            collect($report['rows'])->every(fn (array $row): bool => $row['status'] === 'approved');

        $trend = collect(range(5, 0))->map(function (int $offset) use ($analytics, $companyId, $month): array {
            $trendMonth = Carbon::createFromFormat('!Y-m', $month)
                ->subMonths($offset)
                ->format('Y-m');
            $monthly = $analytics->calculateMonth($companyId, $trendMonth, false);
            $basicAfterDeduction = array_sum(array_map(
                fn (array $row): float => max(0, (float) $row['basic_salary'] - (float) $row['absence_deduction']),
                $monthly['rows']
            ));

            return [
                'month' => $trendMonth,
                'label' => Carbon::createFromFormat('!Y-m', $trendMonth)->format('M Y'),
                'budget' => (float) ($monthly['budget']?->allocated_budget ?? 0),
                'payout' => (float) $monthly['total_payout'],
                'basic_pay' => $basicAfterDeduction,
                'extras' => max(0, (float) $monthly['total_payout'] - $basicAfterDeduction),
                'on_time_rate' => (float) $monthly['on_time_rate'],
                'report_completion_rate' => (float) $monthly['work_report_completion_rate'],
            ];
        })->values();
        $riskChartValues = [
            round($report['risk_factors']['absences'] * 0.35, 2),
            round($report['risk_factors']['late_arrivals'] * 0.25, 2),
            round($report['risk_factors']['missing_reports'] * 0.25, 2),
            round($report['risk_factors']['incomplete_hours'] * 0.15, 2),
        ];

        return view('admin.finance.index', compact(
            'month',
            'payrollMode',
            'report',
            'staff',
            'profiles',
            'advanceRequests',
            'allApproved',
            'trend',
            'riskChartValues'
        ));
    }

    public function updatePayrollMode(Request $request)
    {
        $data = $request->validate([
            'payroll_mode' => ['required', 'in:full_payroll,allowances_only'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        Company::query()
            ->whereKey($request->user()->company_id)
            ->update(['payroll_mode' => $data['payroll_mode']]);

        return redirect()->route('admin.finance.index', ['month' => $data['month'] ?? now()->format('Y-m')])
            ->with('status', 'Organization payroll mode updated.');
    }

    public function saveBudget(Request $request)
    {
        $data = $request->validate([
            'budget_month' => ['required', 'date_format:Y-m'],
            'allocated_budget' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        CompanyFinancialBudget::query()->updateOrCreate(
            [
                'company_id' => $request->user()->company_id,
                'budget_month' => $data['budget_month'],
            ],
            [
                'allocated_budget' => $data['allocated_budget'],
                'currency' => 'TZS',
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()->route('admin.finance.index', ['month' => $data['budget_month']])
            ->with('status', 'Monthly budget saved.');
    }

    public function saveCompensation(Request $request, int $user)
    {
        $staff = User::query()
            ->where('company_id', $request->user()->company_id)
            ->where('role', 'staff')
            ->whereKey($user)
            ->firstOrFail();
        $company = Company::query()->findOrFail($request->user()->company_id);
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'basic_salary' => [
                $company->payroll_mode === 'full_payroll' ? 'required' : 'nullable',
                'numeric',
                'min:0',
                'max:9999999999999.99',
            ],
            'overtime_hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'before_time_bonus_per_day' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'daily_work_bonus_per_report' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'deduct_unexcused_absences' => ['nullable', 'boolean'],
        ]);
        $compensation = [
            'overtime_hourly_rate' => $data['overtime_hourly_rate'],
            'before_time_bonus_per_day' => $data['before_time_bonus_per_day'],
            'daily_work_bonus_per_report' => $data['daily_work_bonus_per_report'],
        ];
        if ($company->payroll_mode === 'full_payroll') {
            $compensation['basic_salary'] = $data['basic_salary'];
            $compensation['deduct_unexcused_absences'] = $request->boolean('deduct_unexcused_absences');
        }
        StaffCompensationProfile::query()->updateOrCreate(
            [
                'company_id' => $request->user()->company_id,
                'user_id' => $staff->id,
            ],
            $compensation
        );

        return redirect()->route('admin.finance.index', ['month' => $data['month']])
            ->with('status', 'Compensation profile saved. Draft payroll estimates are updated when the page is refreshed.');
    }

    public function approve(Request $request, PayrollAnalyticsService $analytics)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $hasPendingAdvances = SalaryAdvanceRequest::query()
            ->where('company_id', $request->user()->company_id)
            ->where('advance_month', $data['month'])
            ->where('status', 'pending')
            ->exists();
        if ($hasPendingAdvances) {
            return redirect()->route('admin.finance.index', ['month' => $data['month']])
                ->with('error', 'Resolve all pending salary advance requests before locking this month’s payroll.');
        }

        $analytics->approveMonth(
            (int) $request->user()->company_id,
            $data['month'],
            (int) $request->user()->id
        );

        return redirect()->route('admin.finance.index', ['month' => $data['month']])
            ->with('status', 'Payroll has been approved and locked for this month.');
    }

    public function approveAdvance(Request $request, int $advance)
    {
        return $this->decideAdvance($request, $advance, 'approved');
    }

    public function rejectAdvance(Request $request, int $advance)
    {
        return $this->decideAdvance($request, $advance, 'rejected');
    }

    private function decideAdvance(Request $request, int $advanceId, string $decision)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $companyId = (int) $request->user()->company_id;

        DB::transaction(function () use ($data, $companyId, $advanceId, $decision, $request): void {
            $advance = SalaryAdvanceRequest::query()
                ->with('user')
                ->where('company_id', $companyId)
                ->where('advance_month', $data['month'])
                ->whereKey($advanceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($advance->status !== 'pending') {
                abort(409, 'This salary advance request has already been decided.');
            }
            if ($decision === 'approved') {
                $payrollApproved = MonthlyPayrollRecord::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $advance->user_id)
                    ->where('payroll_month', $advance->advance_month)
                    ->where('status', 'approved')
                    ->exists();
                if ($payrollApproved) {
                    abort(409, 'This month’s payroll has already been approved; the advance cannot be deducted.');
                }
            }

            $advance->update([
                'status' => $decision,
                'admin_note' => $data['admin_note'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'is_read' => true,
            ]);

            $body = $decision === 'approved'
                ? 'Ombi lako la Salary Advance la TZS '.number_format((float) $advance->amount, 2).
                    ' limeidhinishwa. Tafadhali wasiliana na ofisi/mhasibu kukabidhiwa fedha zako.'
                : 'Ombi lako la Salary Advance limekataliwa.'.
                    (filled($data['admin_note'] ?? null) ? ' '.$data['admin_note'] : '');

            StaffNotification::query()->create([
                'company_id' => $companyId,
                'user_id' => $advance->user_id,
                'title' => $decision === 'approved'
                    ? 'Salary advance approved'
                    : 'Salary advance rejected',
                'body' => $body,
                'is_pushed' => false,
            ]);
        });

        return redirect()->route('admin.finance.index', ['month' => $data['month']])
            ->with('status', 'Salary advance request '.($decision === 'approved' ? 'approved' : 'rejected').'.');
    }

    public function export(Request $request, PayrollAnalyticsService $analytics): StreamedResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $report = $analytics->calculateMonth(
            (int) $request->user()->company_id,
            $data['month'],
            false
        );
        $payrollMode = Company::query()->whereKey($request->user()->company_id)->value('payroll_mode')
            ?? 'full_payroll';
        $filename = 'payroll-'.$data['month'].'.csv';

        return response()->streamDownload(function () use ($report, $payrollMode): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to create the payroll CSV stream.');
            }
            $headers = [
                'Name', 'Email', 'Month', 'Status', 'On-Time Rate (%)',
                'Working Days', 'On-Time Days', 'Before-Time Days', 'Late Days',
                'Absent Days', 'Working Hours', 'Overtime Hours', 'Accepted Reports',
            ];
            if ($payrollMode === 'full_payroll') {
                $headers[] = 'Basic Salary (TZS)';
            }
            array_push(
                $headers,
                'Overtime Pay (TZS)',
                'Allowances (TZS)'
            );
            if ($payrollMode === 'full_payroll') {
                $headers[] = 'Absence Deduction (TZS)';
            }
            array_push(
                $headers,
                'Salary Advance Deduction (TZS)',
                'Net Pay (TZS)',
                'Risk Score',
                'Risk Category'
            );
            fputcsv($output, $headers);
            foreach ($report['rows'] as $row) {
                $name = $row['user']->name;
                if (preg_match('/^[=+\-@]/', $name)) {
                    $name = "'".$name;
                }
                $csvRow = [
                    $name,
                    $row['user']->email,
                    $report['month'],
                    ucfirst($row['status']),
                    $row['on_time_rate'],
                    $row['working_days_count'],
                    $row['on_time_days'],
                    $row['before_time_days'],
                    $row['late_days'],
                    $row['absent_days'],
                    $row['total_working_hours'],
                    $row['overtime_hours'],
                    $row['accepted_work_reports'],
                ];
                if ($payrollMode === 'full_payroll') {
                    $csvRow[] = $row['basic_salary'];
                }
                array_push(
                    $csvRow,
                    $row['overtime_pay'],
                    $row['allowances']
                );
                if ($payrollMode === 'full_payroll') {
                    $csvRow[] = $row['absence_deduction'];
                }
                array_push(
                    $csvRow,
                    $row['salary_advance_deduction'],
                    $row['net_pay'],
                    $row['risk_score'],
                    $row['risk_category']
                );
                fputcsv($output, $csvRow);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
