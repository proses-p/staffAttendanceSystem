<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayrollRecord;
use App\Models\SalaryAdvanceRequest;
use App\Models\User;
use App\Services\PayrollAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceController extends Controller
{
    public function store(Request $request, PayrollAnalyticsService $analytics)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000', 'max:9999999999999.99'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $month = now()->format('Y-m');

        $advance = DB::transaction(function () use ($data, $user, $companyId, $month, $analytics) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $payroll = MonthlyPayrollRecord::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('payroll_month', $month)
                ->first();
            if ($payroll?->status === 'approved') {
                throw ValidationException::withMessages([
                    'amount' => 'Salary advances cannot be requested after this month’s payroll is approved.',
                ]);
            }

            $hasPending = SalaryAdvanceRequest::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('advance_month', $month)
                ->where('status', 'pending')
                ->exists();
            if ($hasPending) {
                throw ValidationException::withMessages([
                    'amount' => 'You already have a pending salary advance request for this month.',
                ]);
            }

            $report = $analytics->calculateMonth($companyId, $month, false, (int) $user->id);
            $row = $report['rows'][0] ?? null;
            if ($row === null) {
                throw ValidationException::withMessages([
                    'amount' => 'Your payroll information is not available for this month.',
                ]);
            }

            $usedAmount = (float) SalaryAdvanceRequest::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('advance_month', $month)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');
            $maxEligible = max(0, ((float) $row['basic_salary'] * 0.5) - $usedAmount);
            if ((float) $data['amount'] > $maxEligible) {
                throw ValidationException::withMessages([
                    'amount' => 'The requested amount exceeds your current eligible maximum of TZS '.
                        number_format($maxEligible, 2).'.',
                ]);
            }

            return SalaryAdvanceRequest::query()->create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'advance_month' => $month,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'Salary advance request submitted.',
            'request' => [
                'id' => $advance->id,
                'amount' => $advance->amount,
                'reason' => $advance->reason,
                'status' => $advance->status,
                'created_at' => $advance->created_at?->toISOString(),
            ],
        ], 201);
    }
}
