<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance &amp; Payroll · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')
        <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-[1600px] space-y-6">
                <header class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">Financial analytics</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Finance &amp; Payroll</h1>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ $payrollMode === 'allowances_only' ? 'Allowances & Extra Duty Disbursement Sheet' : 'Review staff performance, budget exposure, and monthly payroll estimates.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <a href="{{ route('admin.ai-analyst.index', ['month' => $month]) }}" class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-cyan-800 hover:bg-cyan-100">Analyze with AI</a>
                        <form method="GET" action="{{ route('admin.finance.index') }}" class="flex items-end gap-2">
                            <label class="text-xs font-semibold text-slate-600">
                                Payroll month
                                <input type="month" name="month" value="{{ $month }}" required class="mt-1 block rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            </label>
                            <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">View</button>
                        </form>
                    </div>
                </header>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <form method="POST" action="{{ route('admin.finance.payroll-mode.update') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <label class="block text-sm font-semibold text-slate-700 sm:max-w-xl">
                            Organization Payroll Mode
                            <select name="payroll_mode" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="full_payroll" @selected($payrollMode === 'full_payroll')>Private Sector (Full Salary + Allowances)</option>
                                <option value="allowances_only" @selected($payrollMode === 'allowances_only')>Government / Public Institution (Allowances &amp; Extra Duty Only)</option>
                            </select>
                        </label>
                        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Save Payroll Mode</button>
                    </form>
                </section>

                @if (session('status'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Allocated Budget</p>
                        <p class="mt-3 text-2xl font-bold text-slate-900">TZS {{ number_format($report['allocated_budget'], 2) }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $month }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Estimated / Approved Payout</p>
                        <p class="mt-3 text-2xl font-bold text-slate-900">TZS {{ number_format($report['total_payout'], 2) }}</p>
                        <p class="mt-1 text-xs font-semibold {{ $report['budget_variance'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $report['budget_variance'] < 0 ? 'Over budget by' : 'Remaining budget' }}
                            TZS {{ number_format(abs($report['budget_variance']), 2) }}
                        </p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Performance Allowances &amp; Overtime</p>
                        <p class="mt-3 text-2xl font-bold text-slate-900">TZS {{ number_format($report['total_allowances'], 2) }}</p>
                        <p class="mt-1 text-xs text-slate-400">Overtime and approved incentives</p>
                    </article>
                    @php
                        $riskClasses = match ($report['company_risk_category']) {
                            'Low' => 'bg-emerald-100 text-emerald-800',
                            'Moderate' => 'bg-amber-100 text-amber-800',
                            default => 'bg-rose-100 text-rose-800',
                        };
                    @endphp
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-slate-500">Company Monthly Risk Score</p>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $riskClasses }}">{{ $report['company_risk_category'] }} Risk</span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-slate-900">{{ $report['company_risk_score'] }}%</p>
                        <p class="mt-1 text-xs text-slate-400">Weighted operational risk index</p>
                    </article>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                        <h2 class="font-semibold">6-Month Budget vs. Actual Payout</h2>
                        <div class="mt-4 h-72"><canvas id="budgetChart"></canvas></div>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold">Company Risk Score Breakdown</h2>
                        <div class="mt-4 h-72"><canvas id="riskChart"></canvas></div>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
                        <h2 class="font-semibold">Productivity &amp; Attendance vs. Payroll Cost</h2>
                        <div class="mt-4 h-72"><canvas id="productivityChart"></canvas></div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5">
                        <h2 class="text-lg font-semibold">Salary Advance Requests</h2>
                        <p class="mt-1 text-sm text-slate-500">Requests for {{ $month }}. Approved amounts are deducted from the staff member’s net pay.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1050px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Staff</th>
                                    <th class="px-4 py-3">Requested Amount</th>
                                    <th class="px-4 py-3">Reason</th>
                                    <th class="px-4 py-3">Request Date</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Review</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($advanceRequests as $advanceRequest)
                                    @php
                                        $advanceStatusClasses = match ($advanceRequest->status) {
                                            'approved' => 'bg-emerald-100 text-emerald-800',
                                            'rejected' => 'bg-rose-100 text-rose-800',
                                            default => 'bg-amber-100 text-amber-800',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-semibold">{{ $advanceRequest->user?->name ?? 'Former staff' }}</td>
                                        <td class="px-4 py-3 font-semibold">TZS {{ number_format((float) $advanceRequest->amount, 2) }}</td>
                                        <td class="max-w-sm whitespace-normal px-4 py-3">{{ $advanceRequest->reason }}</td>
                                        <td class="px-4 py-3">{{ $advanceRequest->created_at?->format('d M Y, H:i') }}</td>
                                        <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $advanceStatusClasses }}">{{ ucfirst($advanceRequest->status) }}</span></td>
                                        <td class="px-4 py-3">
                                            @if ($advanceRequest->status === 'pending')
                                                <form method="POST" class="min-w-64 space-y-2">
                                                    @csrf
                                                    <input type="hidden" name="month" value="{{ $month }}">
                                                    <input type="text" name="admin_note" maxlength="5000" placeholder="Optional note" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                                    <div class="flex gap-2">
                                                        <button formaction="{{ route('admin.finance.salary-advances.approve', $advanceRequest) }}" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Approve</button>
                                                        <button formaction="{{ route('admin.finance.salary-advances.reject', $advanceRequest) }}" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white">Reject</button>
                                                    </div>
                                                </form>
                                            @else
                                                <span class="text-xs text-slate-500">{{ $advanceRequest->admin_note ?: 'No admin note' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No salary advance requests for this month.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                        <div>
                            <h2 class="text-lg font-semibold">Monthly Financial Budget</h2>
                            <p class="mt-1 text-sm text-slate-500">Set the organization’s TZS allocation for {{ $month }}.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.finance.budget.store') }}" class="grid gap-3 sm:grid-cols-[150px_minmax(220px,1fr)_auto] sm:items-end">
                            @csrf
                            <input type="hidden" name="budget_month" value="{{ $month }}">
                            <label class="text-xs font-semibold text-slate-600">Currency
                                <input value="TZS" disabled class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm">
                            </label>
                            <label class="text-xs font-semibold text-slate-600">Allocated amount
                                <input type="number" name="allocated_budget" min="0" step="0.01" required value="{{ old('allocated_budget', $report['allocated_budget']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <button class="rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white">Save Budget</button>
                            <label class="text-xs font-semibold text-slate-600 sm:col-span-3">Notes
                                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $report['budget']?->notes) }}</textarea>
                            </label>
                        </form>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">Staff Compensation Profiles</h2>
                        <p class="mt-1 text-sm text-slate-500">Compensation changes update draft estimates only; approved payroll remains locked.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @forelse ($staff as $member)
                            @php($profile = $profiles->get($member->id))
                            <details class="rounded-xl border border-slate-200 p-4">
                                <summary class="cursor-pointer font-semibold">{{ $member->name }} <span class="ml-1 text-xs font-normal text-slate-500">· Configure pay</span></summary>
                                <form method="POST" action="{{ route('admin.finance.compensation.update', $member) }}" class="mt-4 space-y-3">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="month" value="{{ $month }}">
                                    @if ($payrollMode === 'full_payroll')
                                        <label class="block text-xs font-semibold text-slate-600">Basic salary (TZS)
                                            <input type="number" name="basic_salary" min="0" step="0.01" required value="{{ old('basic_salary', $profile?->basic_salary ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        </label>
                                    @else
                                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Basic salary and absence deductions are inactive in this payroll mode.</p>
                                    @endif
                                    <label class="block text-xs font-semibold text-slate-600">Overtime hourly rate (TZS)
                                        <input type="number" name="overtime_hourly_rate" min="0" step="0.01" required value="{{ old('overtime_hourly_rate', $profile?->overtime_hourly_rate ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </label>
                                    <label class="block text-xs font-semibold text-slate-600">Before-time bonus per day (TZS)
                                        <input type="number" name="before_time_bonus_per_day" min="0" step="0.01" required value="{{ old('before_time_bonus_per_day', $profile?->before_time_bonus_per_day ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </label>
                                    <label class="block text-xs font-semibold text-slate-600">Accepted work report bonus (TZS)
                                        <input type="number" name="daily_work_bonus_per_report" min="0" step="0.01" required value="{{ old('daily_work_bonus_per_report', $profile?->daily_work_bonus_per_report ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </label>
                                    @if ($payrollMode === 'full_payroll')
                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="deduct_unexcused_absences" value="1" @checked(old('deduct_unexcused_absences', $profile?->deduct_unexcused_absences ?? true)) class="rounded border-slate-300 text-cyan-700">
                                            Deduct unexcused absences
                                        </label>
                                    @endif
                                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save Profile</button>
                                </form>
                            </details>
                        @empty
                            <p class="text-sm text-slate-500">No active staff members in this company.</p>
                        @endforelse
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col justify-between gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center">
                        <div>
                            <h2 class="text-lg font-semibold">Monthly Payroll &amp; Performance Report</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $month }} · {{ count($report['rows']) }} active staff</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.finance.export', ['month' => $month]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV Report</a>
                            @if (!$allApproved && count($report['rows']) > 0)
                                <form method="POST" action="{{ route('admin.finance.approve') }}" onsubmit="return confirm('Approve and lock payroll calculations for {{ $month }}?')">
                                    @csrf
                                    <input type="hidden" name="month" value="{{ $month }}">
                                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Approve &amp; Lock Month Payroll</button>
                                </form>
                            @elseif ($allApproved)
                                <span class="rounded-lg bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-800">Month payroll locked</span>
                            @else
                                <span class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">No staff payroll to approve</span>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1800px] text-left text-xs">
                            <thead class="bg-slate-50 uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-3">Staff</th>
                                    <th class="px-3 py-3">On-Time Rate</th>
                                    <th class="px-3 py-3">Working Days</th>
                                    <th class="px-3 py-3">On-Time</th>
                                    <th class="px-3 py-3">Before-Time</th>
                                    <th class="px-3 py-3">Late</th>
                                    <th class="px-3 py-3">Absent</th>
                                    <th class="px-3 py-3">Working Hrs</th>
                                    <th class="px-3 py-3">Overtime Hrs</th>
                                    <th class="px-3 py-3">Accepted Reports</th>
                                    @if ($payrollMode === 'full_payroll')
                                        <th class="px-3 py-3">Basic Salary</th>
                                    @endif
                                    <th class="px-3 py-3">Overtime Pay</th>
                                    <th class="px-3 py-3">Allowances</th>
                                    @if ($payrollMode === 'full_payroll')
                                        <th class="px-3 py-3">Absence Deduction</th>
                                    @endif
                                    <th class="px-3 py-3">Salary Advance</th>
                                    <th class="px-3 py-3">Net Pay</th>
                                    <th class="px-3 py-3">Risk Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($report['rows'] as $item)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-3 py-3"><span class="font-semibold">{{ $item['user']->name }}</span><span class="mt-1 block text-slate-400">{{ ucfirst($item['status']) }}</span></td>
                                        <td class="px-3 py-3">{{ number_format($item['on_time_rate'], 2) }}%</td>
                                        <td class="px-3 py-3">{{ $item['working_days_count'] }}</td>
                                        <td class="px-3 py-3">{{ $item['on_time_days'] }}</td>
                                        <td class="px-3 py-3">{{ $item['before_time_days'] }}</td>
                                        <td class="px-3 py-3">{{ $item['late_days'] }}</td>
                                        <td class="px-3 py-3">{{ $item['absent_days'] }}</td>
                                        <td class="px-3 py-3">{{ number_format($item['total_working_hours'], 2) }}</td>
                                        <td class="px-3 py-3">{{ number_format($item['overtime_hours'], 2) }}</td>
                                        <td class="px-3 py-3">{{ $item['accepted_work_reports'] }}</td>
                                        @if ($payrollMode === 'full_payroll')
                                            <td class="px-3 py-3">{{ number_format($item['basic_salary'], 2) }}</td>
                                        @endif
                                        <td class="px-3 py-3">{{ number_format($item['overtime_pay'], 2) }}</td>
                                        <td class="px-3 py-3">{{ number_format($item['allowances'], 2) }}</td>
                                        @if ($payrollMode === 'full_payroll')
                                            <td class="px-3 py-3">{{ number_format($item['absence_deduction'], 2) }}</td>
                                        @endif
                                        <td class="px-3 py-3 text-rose-700">{{ number_format($item['salary_advance_deduction'], 2) }}</td>
                                        <td class="px-3 py-3 font-bold">TZS {{ number_format($item['net_pay'], 2) }}</td>
                                        <td class="px-3 py-3"><span class="rounded-full px-2 py-1 font-bold {{ $item['risk_category'] === 'Low' ? 'bg-emerald-100 text-emerald-800' : ($item['risk_category'] === 'Moderate' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">{{ $item['risk_score'] }} · {{ $item['risk_category'] }}</span></td>
                                    </tr>
                                @endforeach
                                @if (count($report['rows']) === 0)
                                    <tr><td colspan="{{ $payrollMode === 'full_payroll' ? 17 : 15 }}" class="px-4 py-12 text-center text-slate-500">No active staff members are available for this payroll period.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        const trend = @json($trend);
        const currency = value => `TZS ${Number(value).toLocaleString('en-TZ', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
        new Chart(document.getElementById('budgetChart'), {
            data: {
                labels: trend.map(item => item.label),
                datasets: [
                    {type: 'bar', label: 'Allocated Budget', data: trend.map(item => item.budget), backgroundColor: '#cbd5e1', order: 3},
                    {type: 'bar', label: @json($payrollMode === 'allowances_only' ? 'Base salary paid externally' : 'Basic Pay after Absence Deduction'), data: trend.map(item => item.basic_pay), backgroundColor: '#0891b2', stack: 'payout', order: 2},
                    {type: 'bar', label: @json($payrollMode === 'allowances_only' ? 'Allowances & Extra Duty' : 'Overtime & Bonuses'), data: trend.map(item => item.extras), backgroundColor: '#fb923c', stack: 'payout', order: 2},
                    {type: 'line', label: 'Actual Net Payout', data: trend.map(item => item.payout), borderColor: '#7c3aed', backgroundColor: '#7c3aed', tension: .3, order: 1}
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: {stacked: true},
                    y: {stacked: true, beginAtZero: true, ticks: {callback: currency}}
                }
            }
        });
        new Chart(document.getElementById('productivityChart'), {
            type: 'line',
            data: {
                labels: trend.map(item => item.label),
                datasets: [
                    {label: 'On-Time Rate (%)', data: trend.map(item => item.on_time_rate), borderColor: '#0284c7', tension: .3, yAxisID: 'y'},
                    {label: 'Work Report Completion (%)', data: trend.map(item => item.report_completion_rate), borderColor: '#16a34a', tension: .3, yAxisID: 'y'},
                    {label: 'Payroll Expenditure (TZS)', data: trend.map(item => item.payout), borderColor: '#ea580c', tension: .3, yAxisID: 'cost'}
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: {beginAtZero: true, max: 100, position: 'left', title: {display: true, text: 'Rate (%)'}},
                    cost: {beginAtZero: true, position: 'right', grid: {drawOnChartArea: false}, ticks: {callback: currency}, title: {display: true, text: 'Payroll (TZS)'}}
                }
            }
        });
        new Chart(document.getElementById('riskChart'), {
            type: 'doughnut',
            data: {
                labels: ['Absences (35%)', 'Late Arrivals (25%)', 'Missing Reports (25%)', 'Incomplete Hours (15%)'],
                datasets: [{data: @json($riskChartValues), backgroundColor: ['#e11d48', '#f97316', '#eab308', '#6366f1']}]
            },
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
        });
    </script>
</body>
</html>
