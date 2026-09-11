<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance | StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    @php
        $formatMinutes = fn ($minutes) => sprintf('%dh %02dm', intdiv((int) $minutes, 60), ((int) $minutes) % 60);
        $formatTime = fn ($time) => $time ? \Carbon\Carbon::parse($time)->format('h:i A') : '—';
        $chartRecords = $records->sortBy('attendance_date')->take(-14);
    @endphp

    <div class="mx-auto min-h-screen max-w-[1500px] px-3 py-3 sm:px-6 sm:py-4 lg:px-8 2xl:max-w-[1800px]">
        <header class="mb-5 flex min-w-0 items-center justify-between gap-3 sm:mb-6 sm:gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="brand-mark flex h-11 w-11 items-center justify-center rounded-2xl text-white"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg></div>
                <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[0.18em] text-orange-600">StaffFlow</p><p class="truncate text-sm text-slate-500">Attendance workspace</p></div>
            </div>
            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-2 shadow-sm transition hover:bg-slate-50 sm:gap-3 sm:px-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-100 text-sm font-bold text-orange-700">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="hidden min-w-0 text-left sm:block"><span class="block max-w-[9rem] truncate text-sm font-semibold">{{ auth()->user()->name }}</span><span class="block text-xs text-slate-500">Staff member</span></span><svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></summary>
                <div class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 text-left shadow-xl"><div class="border-b border-slate-100 px-3 py-2"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div><form method="POST" action="{{ route('logout', [], false) }}" class="mt-1">@csrf<button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M15 12H3m0 0 4-4m-4 4 4 4M13 5V3h8v18h-8v-2" /></svg>Log out</button></form></div>
            </details>
        </header>

        <main>
            <section class="mb-6 grid gap-4 lg:grid-cols-[1.35fr_.65fr]">
                <div class="hero-panel min-w-0 overflow-hidden rounded-3xl p-5 text-white shadow-xl sm:p-8"><div class="relative z-10 flex h-full flex-col justify-between gap-7 sm:gap-8"><div><p class="text-sm font-semibold text-orange-100">{{ now()->format('l, d F Y') }}</p><h1 class="mt-3 max-w-xl break-words text-2xl font-bold tracking-tight sm:text-4xl">Good day, {{ auth()->user()->name }}.</h1><p class="mt-3 max-w-lg text-sm leading-6 text-white/75">Keep your attendance record complete and your workday easy to understand.</p></div><div class="flex flex-wrap items-end justify-between gap-3 sm:gap-4"><div><p class="text-xs font-semibold uppercase tracking-widest text-white/60">Current time</p><p id="live-clock" class="mt-1 text-2xl font-semibold tabular-nums sm:text-3xl">{{ now()->format('h:i A') }}</p></div><span class="max-w-full rounded-full border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold">{{ $attendance ? 'Attendance in progress' : 'Ready to sign in' }}</span></div></div></div>
                <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-bold uppercase tracking-widest text-slate-400">Today</p><h2 class="mt-2 text-xl font-bold">Your workday</h2></div><span class="status-dot shrink-0 {{ $attendance?->check_out_time ? 'status-done' : ($attendance ? 'status-live' : 'status-idle') }}"></span></div><div class="mt-5 grid grid-cols-2 gap-3 sm:mt-6 sm:gap-4"><div class="min-w-0"><p class="text-xs text-slate-500">Signed in</p><p class="mt-1 truncate text-base font-bold sm:text-lg">{{ $formatTime($attendance?->check_in_time) }}</p></div><div class="min-w-0"><p class="text-xs text-slate-500">Signed out</p><p class="mt-1 truncate text-base font-bold sm:text-lg">{{ $formatTime($attendance?->check_out_time) }}</p></div></div><div class="mt-5 border-t border-slate-100 pt-4 sm:mt-6"><p class="text-xs text-slate-500">Working hours</p><p class="mt-1 break-words text-xl font-bold text-orange-600 sm:text-2xl">{{ $attendance?->work_duration ?? 'Not finished' }}</p></div></div>
            </section>

            <section class="mb-6 grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['Today’s hours', $formatMinutes($stats['today_minutes']), 'clock'],
                        ['Average hours', $formatMinutes($stats['average_minutes']), 'trend'],
                        ['Attendance rate', $stats['attendance_rate'] . '%', 'calendar'],
                        ['On-time rate', $stats['on_time_rate'] . '%', 'check']
                    ] as [$label, $value, $icon])
                        <div class="stat-card min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <div class="flex items-center justify-between">
                                <p class="min-w-0 truncate text-sm font-medium text-slate-500">{{ $label }}</p>
                                <span class="stat-icon">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon === 'clock' ? 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' : ($icon === 'trend' ? 'M4 16l5-5 4 3 7-8' : ($icon === 'calendar' ? 'M7 3v3m10-3v3M4 9h16m-2 11H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2Z' : 'M5 12l4 4L19 6')) }}" />
                                    </svg>
                                </span>
                            </div>
                            <p class="mt-3 text-2xl font-bold tracking-tight">{{ $value }}</p>
                        </div>
                    @endforeach

            </section>

            

            <section class="mb-6 grid min-w-0 gap-6 xl:grid-cols-[.95fr_1.05fr]">
                <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-bold uppercase tracking-widest text-orange-600">Attendance action</p><h2 class="mt-2 break-words text-xl font-bold sm:text-2xl">{{ $attendance ? ($attendance->check_out_time ? 'Day complete' : 'You are checked in') : 'Start your day' }}</h2></div><span class="shrink-0 rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-orange-700">{{ $attendance?->status ? ucfirst($attendance->status) : 'Pending' }}</span></div><p id="attendance-message" class="mt-3 text-sm leading-6 text-slate-500">{{ $attendance ? ($attendance->check_out_time ? 'Your attendance has been recorded for today.' : 'Remember to sign out when your workday is complete.') : 'We will verify your location before recording your sign in.' }}</p>
                    @if (!$attendance)<button id="sign-in-button" type="button" class="brand-button mt-6 flex w-full items-center justify-center gap-2 rounded-xl py-3.5 text-sm font-bold text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M12 5v14m-7-7h14" /></svg>Sign in now</button>@elseif (!$attendance->check_out_time)<button id="sign-out-button" type="button" class="brand-button mt-6 flex w-full items-center justify-center gap-2 rounded-xl py-3.5 text-sm font-bold text-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>Sign out</button>@else<div class="mt-6 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100">✓</span>Complete for today</div>@endif
                    <div id="location-message" class="mt-3"></div>
                </div>
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    @foreach ([['Sign-in location', $attendance?->sign_in_location, $attendance?->latitude, $attendance?->longitude, 'bg-orange-50', 'text-orange-600'], ['Sign-out location', $attendance?->sign_out_location, $attendance?->check_out_latitude, $attendance?->check_out_longitude, 'bg-emerald-50', 'text-emerald-600']] as [$label, $location, $latitude, $longitude, $bg, $color])
                        <div class="location-card min-w-0 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><div class="flex min-w-0 items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $bg }} {{ $color }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.1 7-11a7 7 0 1 0-14 0c0 5.9 7 11 7 11Z" /><circle cx="12" cy="10" r="2.2" /></svg></span><div class="min-w-0"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p><p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $location ?? 'Awaiting attendance' }}</p></div></div>@if ($latitude && $longitude)<div class="mini-map mt-5"><span class="map-pin"></span><a href="https://www.openstreetmap.org/?mlat={{ $latitude }}&mlon={{ $longitude }}#map=17/{{ $latitude }}/{{ $longitude }}" target="_blank" rel="noreferrer">Open map</a></div>@else<div class="mini-map mini-map-empty mt-5">No location recorded yet</div>@endif</div>
                    @endforeach
                </div>
            </section>

            <section class="mb-6 grid min-w-0 gap-4 sm:gap-6 lg:grid-cols-3">
                @foreach ([['Working hours per day', 'hours-chart'], ['Sign-in time trend', 'signin-chart'], ['Sign-out time trend', 'signout-chart']] as [$title, $id])<div class="chart-panel min-w-0 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><h3 class="truncate text-sm font-bold">{{ $title }}</h3><div class="relative mt-4 h-40 w-full sm:h-44"><canvas id="{{ $id }}"></canvas></div></div>@endforeach
            </section>

          <section class="min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-6 sm:py-5"><div><p class="text-xs font-bold uppercase tracking-widest text-orange-600">Your records</p><h2 class="mt-1 text-xl font-bold">Attendance history</h2></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">{{ $records->count() }} records</span></div><div class="max-w-full overflow-x-auto"><table class="min-w-[980px] w-full text-left"><thead class="bg-slate-50 text-xs uppercase tracking-wider"><tr><th class="px-6 py-4 font-bold">Date</th><th class="px-4 py-4 font-bold">Sign in</th><th class="px-4 py-4 font-bold">Sign out</th><th class="px-4 py-4 font-bold">Working hours</th><th class="px-4 py-4 font-bold">Status</th><th class="px-4 py-4 font-bold">Sign-in location</th><th class="px-6 py-4 font-bold">Sign-out location</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse ($records as $record)<tr class="table-row"><td class="px-6 py-4"><p class="text-sm font-bold">{{ $record->attendance_date->format('d M Y') }}</p><p class="text-xs text-slate-400">{{ $record->attendance_date->format('l') }}</p></td><td class="px-4 py-4 text-sm font-semibold">{{ $formatTime($record->check_in_time) }}</td><td class="px-4 py-4 text-sm font-semibold">{{ $formatTime($record->check_out_time) }}</td><td class="px-4 py-4 text-sm font-semibold">{{ $record->work_duration ?? 'In progress' }}</td><td class="px-4 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ ucfirst($record->status) }}</span></td><td class="max-w-[210px] break-words px-4 py-4 text-sm text-slate-600">{{ $record->sign_in_location ?? 'Unavailable' }}</td><td class="max-w-[210px] break-words px-6 py-4 text-sm text-slate-600">{{ $record->sign_out_location ?? '—' }}</td></tr>@empty<tr><td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">Your attendance history will appear here after your first sign in.</td></tr>@endforelse</tbody></table></div></section>
            
            <div class="mt-4">
                {{ $records->links() }}
            </div>

        </main>

    </div>

    <script>
        const csrfToken = '{{ csrf_token() }}';
        const checkInUrl = '{{ route('attendance.check-in', [], false) }}';
        const checkOutUrl = '{{ route('attendance.check-out', [], false) }}';
                <?php
            $formattedRecords = $chartRecords->values()->map(fn ($record) => [
                'date' => $record->attendance_date->format('d M'),
                'hours' => $record->check_in_time && $record->check_out_time ? round(\Carbon\Carbon::parse($record->check_in_time)->diffInMinutes(\Carbon\Carbon::parse($record->check_out_time)) / 60, 2) : 0,
                'signIn' => $record->check_in_time ? \Carbon\Carbon::parse($record->check_in_time)->format('H:i') : null,
                'signOut' => $record->check_out_time ? \Carbon\Carbon::parse($record->check_out_time)->format('H:i') : null
            ]);
        ?>
        const chartRecords = @json($formattedRecords);

        const labels = chartRecords.map(record => record.date);
        const timeToMinutes = time => time ? Number(time.split(':')[0]) * 60 + Number(time.split(':')[1]) : null;
        const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { grid: { color: '#eef0f3' }, ticks: { font: { size: 10 } } } } };
        const createChart = (id, data, color, suffix = '') => new Chart(document.getElementById(id), { type: 'line', data: { labels, datasets: [{ data, borderColor: color, backgroundColor: color + '18', fill: true, tension: .38, pointRadius: 3, pointBackgroundColor: color }] }, options: { ...chartOptions, plugins: { ...chartOptions.plugins, tooltip: { callbacks: { label: context => `${context.raw ?? 0}${suffix}` } } } } });
        createChart('hours-chart', chartRecords.map(record => record.hours), '#e2573f', ' h');
        createChart('signin-chart', chartRecords.map(record => timeToMinutes(record.signIn)), '#e98a3a', ' min');
        createChart('signout-chart', chartRecords.map(record => timeToMinutes(record.signOut)), '#2f9b72', ' min');
        setInterval(() => { document.getElementById('live-clock').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }, 1000);
        const showLocationMessage = (title, description, tone = 'info') => { const target = document.getElementById('location-message'); target.innerHTML = `<div class="rounded-xl border px-4 py-3 text-sm ${tone === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-orange-200 bg-orange-50 text-orange-700'}"><strong>${title}</strong><p class="mt-1">${description}</p></div>`; };
        const submitAttendance = (url, button) => { if (!navigator.geolocation) { showLocationMessage('Location unavailable', 'Your browser does not support location services.', 'error'); return; } button.disabled = true; button.textContent = 'Getting location...'; navigator.geolocation.getCurrentPosition(position => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy }) }).then(async response => ({ status: response.status, data: await response.json() })).then(result => { if (result.data.success) { window.location.reload(); return; } button.disabled = false; button.textContent = url === checkInUrl ? 'Sign in now' : 'Sign out'; showLocationMessage('Unable to update attendance', result.data.message || 'Please try again.', 'error'); }).catch(() => { button.disabled = false; button.textContent = url === checkInUrl ? 'Sign in now' : 'Sign out'; showLocationMessage('Connection error', 'Unable to connect to the server. Please try again.', 'error'); }), () => { button.disabled = false; button.textContent = url === checkInUrl ? 'Sign in now' : 'Sign out'; showLocationMessage('Location permission required', 'Allow location access to record attendance.', 'error'); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }); };
        document.getElementById('sign-in-button')?.addEventListener('click', event => submitAttendance(checkInUrl, event.currentTarget));
        document.getElementById('sign-out-button')?.addEventListener('click', event => submitAttendance(checkOutUrl, event.currentTarget));
    </script>
</body>
</html>
