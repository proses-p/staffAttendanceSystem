@php
    $signedIn = $onTime + $late;
    $coveragePercent = $totalStaff > 0 ? number_format(($signedIn / $totalStaff) * 100, 1) : '0';
    $onTimeWidth = $totalStaff > 0 ? ($onTime / $totalStaff) * 100 : 0;
    $lateWidth = $totalStaff > 0 ? ($late / $totalStaff) * 100 : 0;
    $notSignedInWidth = $totalStaff > 0 ? ($notSignedIn / $totalStaff) * 100 : 0;
    $onTimeAngle = min(360, $onTimeWidth * 3.6);
    $signedInAngle = min(360, $coveragePercent * 3.6);
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-slate-950 p-5 text-white shadow-[0_12px_30px_rgba(15,23,42,0.12)]">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-300">Total staff</p>
                <span class="rounded-lg bg-white/10 p-2 text-slate-200">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m5-4a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6 0a4 4 0 0 0-1.75.4" /></svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight">{{ $totalStaff }}</p>
            <p class="mt-1 text-xs text-slate-400">Active employees</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Signed in</p>
                <span class="rounded-lg bg-cyan-50 p-2 text-cyan-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-slate-950">{{ $signedIn }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $coveragePercent }}% coverage today</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">On time</p>
                <span class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-slate-950">{{ $isConfigured ? $onTime : '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $isConfigured ? $onTimePercent . '% of staff' : 'Configure arrival time' }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Needs attention</p>
                <span class="rounded-lg bg-rose-50 p-2 text-rose-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.4 14.55A1.5 1.5 0 0 0 3.19 21h17.62a1.5 1.5 0 0 0 1.3-2.59L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" /></svg>
                </span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-slate-950">{{ $notSignedIn + $late }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $late }} late · {{ $notSignedIn }} not signed in</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Today's pulse</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Attendance mix</h2>
                    <p class="mt-1 text-sm text-slate-500">A live view of today's staff arrival status.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $signedIn }} of {{ $totalStaff }} present</span>
            </div>

            <div class="mt-8 flex flex-col items-center gap-8 sm:flex-row sm:items-center">
                <div class="relative h-44 w-44 shrink-0 rounded-full" style="background: conic-gradient(#10b981 0deg {{ $onTimeAngle }}deg, #f59e0b {{ $onTimeAngle }}deg {{ $signedInAngle }}deg, #e2e8f0 {{ $signedInAngle }}deg 360deg);">
                    <div class="absolute inset-5 flex flex-col items-center justify-center rounded-full bg-white">
                        <span class="text-3xl font-semibold text-slate-950">{{ $coveragePercent }}%</span>
                        <span class="mt-1 text-xs text-slate-500">coverage</span>
                    </div>
                </div>
                <div class="w-full space-y-4">
                    <div class="flex items-center justify-between text-sm"><span class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>On time</span><span class="font-semibold text-slate-950">{{ $onTime }}</span></div>
                    <div class="flex items-center justify-between text-sm"><span class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Late</span><span class="font-semibold text-slate-950">{{ $late }}</span></div>
                    <div class="flex items-center justify-between text-sm"><span class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-slate-200"></span>Not signed in</span><span class="font-semibold text-slate-950">{{ $notSignedIn }}</span></div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Performance</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Arrival performance</h2>
                    <p class="mt-1 text-sm text-slate-500">How the team is tracking against the schedule.</p>
                </div>
                <svg class="h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M7 16l3-4 3 2 5-7" /></svg>
            </div>

            <div class="mt-8 space-y-6">
                <div>
                    <div class="mb-2 flex justify-between text-sm"><span class="font-medium text-slate-700">On time arrivals</span><span class="text-slate-500">{{ $onTimePercent }}%</span></div>
                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, $onTimeWidth) }}%"></div></div>
                </div>
                <div>
                    <div class="mb-2 flex justify-between text-sm"><span class="font-medium text-slate-700">Late arrivals</span><span class="text-slate-500">{{ $latePercent }}%</span></div>
                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-amber-400" style="width: {{ min(100, $lateWidth) }}%"></div></div>
                </div>
                <div>
                    <div class="mb-2 flex justify-between text-sm"><span class="font-medium text-slate-700">Not signed in</span><span class="text-slate-500">{{ $notSignedInPercent }}%</span></div>
                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-slate-400" style="width: {{ min(100, $notSignedInWidth) }}%"></div></div>
                </div>
            </div>

            <div class="mt-8 flex items-center gap-3 rounded-xl bg-slate-50 p-4">
                <span class="rounded-lg bg-white p-2 text-slate-500 shadow-sm"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg></span>
                <div class="text-sm"><p class="font-medium text-slate-700">Expected arrival</p><p class="text-slate-500">{{ $isConfigured ? $expectedArrivalTimeFormatted : 'Time interval not configured' }}</p></div>
            </div>
        </section>
    </div>
</div>
