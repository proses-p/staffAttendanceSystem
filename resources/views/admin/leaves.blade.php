<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leaves</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="min-h-screen flex flex-col lg:flex-row">
    @include('components.admin-sidebar')
    <main class="flex-1 p-4 sm:p-6 lg:p-10">
        <div class="mx-auto max-w-5xl space-y-7">
            <header class="border-b border-slate-200 pb-6">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">Workspace requests</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Manage Leaves</h1>
                <p class="mt-2 text-sm text-slate-500">Review staff leave requests, record leave, and respond to pending requests.</p>
            </header>

            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold">Leave management</h2>
                <p class="mt-1 text-sm text-slate-500">Only approved leave is excluded from scheduled-day denominators and counted in leave days. Each scheduled, non-holiday date in an approved range counts as one leave day.</p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 font-medium text-slate-700">
                            {{ $label }}: {{ $leaveCounts[$value] ?? 0 }}
                        </span>
                    @endforeach
                </div>
                @if ($staff->isEmpty())
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Add staff accounts before recording leave.</p>
                @else
                    <h3 class="mt-6 text-sm font-semibold text-slate-800">Record leave for a staff member</h3>
                    <form method="POST" action="{{ route('admin.leave-records.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        @csrf
                        <select name="user_id" required class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="">Select staff</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected(old('user_id') == $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="start_date" required value="{{ old('start_date') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm" aria-label="Leave start date">
                        <input type="date" name="end_date" required value="{{ old('end_date') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm" aria-label="Leave end date">
                        <input type="text" name="reason" maxlength="2000" value="{{ old('reason') }}" placeholder="Reason (optional)" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        <select name="status" required class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="brand-button rounded-xl px-4 py-2.5 text-sm font-semibold text-white">Record leave</button>
                    </form>
                @endif
                <form method="GET" action="{{ route('admin.leaves.index') }}" class="mt-7 flex flex-wrap items-center gap-2">
                    <label for="leave-status" class="text-sm font-medium text-slate-700">Filter requests</label>
                    <select id="leave-status" name="leave_status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected($leaveStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">Apply</button>
                </form>
                <div class="mt-4 space-y-3">
                    @forelse ($leaveRecords as $leave)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="text-sm">
                                    <p class="font-semibold">{{ $leave->user?->name ?? 'Deleted staff account' }}</p>
                                    <p class="mt-1 text-slate-600">{{ $leave->leave_type }} · {{ $leave->start_date->format('M j, Y') }} – {{ $leave->end_date->format('M j, Y') }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $leave->request_source === 'staff_request' ? 'Staff request' : 'Admin record' }}
                                        · Submitted {{ $leave->created_at?->format('M j, Y') ?? 'date unavailable' }}
                                        @if ($leave->user?->email) · {{ $leave->user->email }} @endif
                                    </p>
                                </div>
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-amber-100 text-amber-800' => $leave->status === 'pending',
                                    'bg-emerald-100 text-emerald-800' => $leave->status === 'approved',
                                    'bg-rose-100 text-rose-800' => $leave->status === 'rejected',
                                ])>{{ ucfirst($leave->status) }}</span>
                            </div>
                            <details class="mt-3 text-sm">
                                <summary class="cursor-pointer font-medium text-cyan-700">View request details</summary>
                                <div class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3 text-slate-700">
                                    <p><span class="font-medium">Reason:</span> {{ $leave->reason ?: 'No reason provided for this record.' }}</p>
                                    @if ($leave->decision_note)
                                        <p><span class="font-medium">Administrator note:</span> {{ $leave->decision_note }}</p>
                                    @endif
                                </div>
                            </details>
                            @if ($leave->status === 'pending')
                                <form method="POST" action="{{ route('admin.leave-records.update', $leave) }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_2fr_auto]">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm" aria-label="Decision">
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                    <input type="text" name="decision_note" maxlength="1000" placeholder="Decision note (optional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <button type="submit" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Save decision</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No leave records match this filter.</p>
                    @endforelse
                </div>
                <div class="mt-5">{{ $leaveRecords->links() }}</div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
