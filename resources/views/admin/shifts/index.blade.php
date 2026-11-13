<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shifts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="min-h-screen flex flex-col lg:flex-row">
    @include('components.admin-sidebar')
    <main class="flex-1 p-4 sm:p-6 lg:p-10">
        <div class="mx-auto max-w-6xl space-y-7">
            <header class="border-b border-slate-200 pb-6">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">Workspace schedule</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Manage shifts</h1>
                <p class="mt-2 text-sm text-slate-500">Create company shifts and assign each staff member a schedule. Staff without an assigned shift use the company attendance policy.</p>
            </header>

            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold">Create a shift</h2>
                <form method="POST" action="{{ route('admin.shifts.store') }}" class="mt-5 space-y-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-sm font-medium text-slate-700">Shift name
                            <input name="name" required maxlength="120" value="{{ old('name') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                        <label class="text-sm font-medium text-slate-700">Start time
                            <input type="time" name="start_time" required value="{{ old('start_time') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                        <label class="text-sm font-medium text-slate-700">End time
                            <input type="time" name="end_time" required value="{{ old('end_time') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                        <label class="text-sm font-medium text-slate-700">Grace period (minutes)
                            <input type="number" name="grace_period_minutes" min="0" max="240" required value="{{ old('grace_period_minutes', 15) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700">Working days</legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($weekdays as $day => $label)
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="working_days[]" value="{{ $day }}" @checked(in_array($day, old('working_days', []), true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="flex justify-end">
                        <button class="brand-button rounded-xl px-5 py-3 text-sm font-semibold text-white" type="submit">Create shift</button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold">Existing shifts</h2>
                <div class="mt-4 space-y-4">
                    @forelse ($shifts as $shift)
                        <div class="rounded-xl border border-slate-200 p-4">
                        <form method="POST" action="{{ route('admin.shifts.update', $shift) }}">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="text-sm text-slate-700">Name
                                    <input name="name" required maxlength="120" value="{{ $shift->name }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                </label>
                                <label class="text-sm text-slate-700">Start
                                    <input type="time" name="start_time" required value="{{ $shift->start_time->format('H:i') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                </label>
                                <label class="text-sm text-slate-700">End
                                    <input type="time" name="end_time" required value="{{ $shift->end_time->format('H:i') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                </label>
                                <label class="text-sm text-slate-700">Grace period (minutes)
                                    <input type="number" name="grace_period_minutes" min="0" max="240" required value="{{ $shift->grace_period_minutes }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                </label>
                            </div>
                            <fieldset class="mt-4">
                                <legend class="text-sm font-medium text-slate-700">Working days</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($weekdays as $day => $label)
                                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input type="checkbox" name="working_days[]" value="{{ $day }}" @checked(in_array($day, $shift->working_days ?? [], true))>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <span class="text-xs text-slate-500">{{ $shift->users_count }} staff assigned</span>
                                <div class="flex gap-2">
                                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" type="submit">Save changes</button>
                                </div>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.shifts.destroy', $shift) }}" class="mt-3 flex justify-end" onsubmit="return confirm('Delete this shift? Assigned staff will use the company attendance policy.');">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700" type="submit">Delete</button>
                        </form>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No shifts have been created.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold">Staff shift assignments</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($staff as $member)
                        <form method="POST" action="{{ route('admin.shifts.assign') }}" class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $member->id }}">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-900">{{ $member->name }}</p>
                                <p class="text-xs text-slate-500">{{ $member->email }}</p>
                            </div>
                            <select name="shift_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm sm:min-w-64">
                                <option value="">Company default attendance policy</option>
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}" @selected((int) $member->shift_id === (int) $shift->id)>{{ $shift->name }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" type="submit">Assign</button>
                        </form>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No staff members are assigned to this company.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
