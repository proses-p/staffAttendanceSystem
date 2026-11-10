<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Policy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="min-h-screen flex flex-col lg:flex-row">
    @include('components.admin-sidebar')
    <main class="flex-1 p-4 sm:p-6 lg:p-10">
        <div class="mx-auto max-w-5xl space-y-7">
            <header class="border-b border-slate-200 pb-6">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">Workspace preferences</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Attendance policy</h1>
                <p class="mt-2 text-sm text-slate-500">Configure your organization's actual working schedule. Statistics use the application timezone: {{ config('app.timezone') }}.</p>
            </header>

            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold">Working hours and days</h2>
                <p class="mt-1 text-sm text-slate-500">These values determine overtime and eligible days for attendance and punctuality. Work duration is measured from check-in to check-out; breaks are not deducted because break records are not currently captured.</p>
                <form method="POST" action="{{ route('admin.time-settings.store') }}" class="mt-6 space-y-6">
                    @csrf
                    <div class="grid gap-5 sm:grid-cols-3">
                        <label class="block text-sm font-medium text-slate-700">
                            Official start time
                            <input type="time" name="expected_arrival_time" required value="{{ old('expected_arrival_time', $setting?->expected_arrival_time?->format('H:i')) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            Expected work hours per day
                            <input type="number" name="expected_work_hours" min="0.25" max="24" step="0.25" required value="{{ old('expected_work_hours', $setting?->expected_work_minutes ? number_format($setting->expected_work_minutes / 60, 2, '.', '') : '') }}" placeholder="e.g. 8" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            Arrival grace period (minutes)
                            <input type="number" name="grace_period_minutes" min="0" max="240" step="1" required value="{{ old('grace_period_minutes', $setting?->grace_period_minutes ?? 0) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </label>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700">Scheduled working days</legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($weekdays as $number => $day)
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="working_days[]" value="{{ $number }}" @checked(in_array((string) $number, old('working_days', array_map('strval', $setting?->working_days ?? [])), true))>
                                    {{ $day }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <p class="text-xs text-slate-500">Overtime is completed recorded time exceeding the daily expected work hours, on scheduled non-holiday days. No separate overtime multiplier/premium is applied.</p>
                    <div class="flex justify-end">
                        <button type="submit" class="brand-button rounded-xl px-5 py-3 text-sm font-semibold text-white">Save attendance policy</button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-semibold">Holidays</h2>
                <p class="mt-1 text-sm text-slate-500">Holidays are excluded from scheduled-day denominators and daily overtime eligibility.</p>
                <form method="POST" action="{{ route('admin.holidays.store') }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_2fr_auto]">
                    @csrf
                    <input type="date" name="holiday_date" required value="{{ old('holiday_date') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <input type="text" name="name" required maxlength="120" placeholder="Holiday name" value="{{ old('name') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <button type="submit" class="brand-button rounded-xl px-4 py-2.5 text-sm font-semibold text-white">Add holiday</button>
                </form>
                <div class="mt-5 divide-y divide-slate-100">
                    @forelse ($holidays as $holiday)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span><strong>{{ $holiday->holiday_date->format('M j, Y') }}</strong> <span class="text-slate-500">{{ $holiday->name }}</span></span>
                            <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-rose-600 hover:text-rose-800" type="submit">Remove</button>
                            </form>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No holidays recorded.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        </div>
    </main>
</div>
</body>
</html>
