<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyFinancialBudget;
use App\Models\Company;
use App\Models\MonthlyPayrollRecord;
use App\Models\SalaryAdvanceRequest;
use App\Services\PayrollAnalyticsService;
use Illuminate\Http\Request;

class MyPayslipController extends Controller
{
    public function show(Request $request, PayrollAnalyticsService $analytics)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $month = $validated['month'] ?? now()->format('Y-m');
        $payrollMode = Company::query()->whereKey($companyId)->value('payroll_mode') ?? 'full_payroll';
        $allowancesOnly = $payrollMode === 'allowances_only';

        $record = MonthlyPayrollRecord::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('payroll_month', $month)
            ->where('status', 'approved')
            ->first();

        if ($record !== null) {
            $values = $record->only([
                'basic_salary',
                'overtime_pay',
                'punctuality_allowance',
                'work_report_allowance',
                'absence_deduction',
                'salary_advance_deduction',
                'net_pay',
                'on_time_days',
                'before_time_days',
                'late_days',
                'absent_days',
                'overtime_hours',
                'accepted_work_reports',
                'on_time_rate',
                'risk_score',
            ]);
            $status = 'approved';
        } else {
            $report = $analytics->calculateMonth($companyId, $month, false, (int) $user->id);
            $row = $report['rows'][0] ?? null;
            if ($row === null) {
                abort(404, 'Payroll estimate is not available for this month.');
            }

            $values = collect($row)->only([
                'basic_salary',
                'overtime_pay',
                'punctuality_allowance',
                'work_report_allowance',
                'absence_deduction',
                'salary_advance_deduction',
                'net_pay',
                'on_time_days',
                'before_time_days',
                'late_days',
                'absent_days',
                'overtime_hours',
                'accepted_work_reports',
                'on_time_rate',
                'risk_score',
            ])->all();
            $status = 'estimated';
        }

        if ($allowancesOnly) {
            $values['basic_salary'] = 0;
            $values['absence_deduction'] = 0;
            $values['net_pay'] = round(max(
                0,
                (float) ($values['overtime_pay'] ?? 0) +
                (float) ($values['punctuality_allowance'] ?? 0) +
                (float) ($values['work_report_allowance'] ?? 0) -
                (float) ($values['salary_advance_deduction'] ?? 0)
            ), 2);
        }

        $currency = CompanyFinancialBudget::query()
            ->where('company_id', $companyId)
            ->where('budget_month', $month)
            ->value('currency') ?? 'TZS';
        $advanceRequests = SalaryAdvanceRequest::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('advance_month', $month)
            ->latest()
            ->get(['id', 'amount', 'reason', 'status', 'admin_note', 'created_at']);
        $usedAdvanceAmount = (float) SalaryAdvanceRequest::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('advance_month', $month)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');
        $maxEligibleAdvance = max(0, ((float) ($values['basic_salary'] ?? 0) * 0.5) - $usedAdvanceAmount);

        return response()->json([
            'month' => $month,
            'payroll_mode' => $payrollMode,
            'currency' => $currency,
            'status' => $status,
            'salary_advance_deduction' => (float) SalaryAdvanceRequest::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('advance_month', $month)
                ->where('status', 'approved')
                ->sum('amount'),
            'max_eligible_advance' => round($maxEligibleAdvance, 2),
            'advance_requests' => $advanceRequests,
            ...$values,
        ]);
    }
}
