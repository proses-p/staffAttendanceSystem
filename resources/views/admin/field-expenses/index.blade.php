<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field reimbursements · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="min-h-screen flex flex-col lg:flex-row">
    @include('components.admin-sidebar')
    <main class="min-w-0 flex-1 p-4 pt-8 sm:p-6 lg:p-10">
        <div class="mx-auto max-w-7xl">
            <header class="mb-7 flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Financial review</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Pending Field Reimbursements</h1>
                    <p class="mt-2 text-sm text-slate-500">Review staff claims against their field assignment evidence.</p>
                </div>
                <span class="w-fit rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-800">
                    {{ number_format($pendingCount) }} pending
                </span>
            </header>

            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-semibold text-slate-600">Status
                    <select name="status" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Category
                    <select name="category" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All categories</option>
                        @foreach (['transport_fuel' => 'Transport / Fuel', 'meals_refreshments' => 'Meals / Refreshments', 'client_entertainment' => 'Client Entertainment', 'other_emergency' => 'Other / Emergency'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Employee
                    <select name="employee" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) ($filters['employee'] ?? '') === (string) $employee->id)>{{ $employee->name }}{{ $employee->employee_id ? ' · '.$employee->employee_id : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Assignment ID
                    <input type="number" min="1" name="field_assignment_id" value="{{ $filters['field_assignment_id'] ?? '' }}" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">Submitted from
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">Submitted through
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <div class="flex items-end gap-2 sm:col-span-2">
                    <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Apply filters</button>
                    <a href="{{ route('admin.field-expenses.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-5 py-3">Employee</th>
                                <th class="px-5 py-3">Category</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3">Submitted</th>
                                <th class="px-5 py-3">Assignment</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($expenses as $expense)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-slate-900">{{ $expense->user?->name ?? 'Unknown employee' }}</div>
                                        <div class="text-xs text-slate-500">{{ $expense->user?->employee_id ?: 'User #'.$expense->user_id }}</div>
                                    </td>
                                    <td class="px-5 py-4">{{ ['transport_fuel' => 'Transport / Fuel', 'meals_refreshments' => 'Meals / Refreshments', 'client_entertainment' => 'Client Entertainment', 'other_emergency' => 'Other / Emergency'][$expense->category] ?? $expense->category }}</td>
                                    <td class="px-5 py-4 font-semibold">{{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $expense->created_at?->format('d M Y, H:i') }}</td>
                                    <td class="px-5 py-4 text-slate-600">#{{ $expense->field_assignment_id }} · {{ str_replace('_', ' ', $expense->fieldAssignment?->task_type ?? 'field assignment') }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $expense->status === 'pending' ? 'bg-amber-100 text-amber-800' : ($expense->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">
                                            {{ ucfirst($expense->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('admin.field-expenses.show', $expense) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">View details</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No field expense claims match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $expenses->links() }}</div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
