<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $staff->name }} | Staff Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-6 lg:p-10">
    <main class="mx-auto max-w-4xl">
        <header class="mb-8 flex flex-col gap-4 border-b border-slate-200 pb-7 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">
                    <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                    Staff details
                </p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                    {{ $staff->name }}
                </h1>
                <p class="mt-2 text-sm text-slate-500">Account information</p>
            </div>

            <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-800">
                Back to users
            </a>
        </header>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <dl class="divide-y divide-slate-100">
                <div class="grid gap-1 px-6 py-5 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-500">Full Name</dt>
                    <dd class="text-sm font-semibold text-slate-900 sm:col-span-2">{{ $staff->name }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-5 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-500">Email</dt>
                    <dd class="text-sm text-slate-900 sm:col-span-2">{{ $staff->email }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-5 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-500">Organization</dt>
                    <dd class="text-sm text-slate-900 sm:col-span-2">{{ $staff->organization ?? 'Not provided' }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-5 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-500">Role</dt>
                    <dd class="text-sm capitalize text-slate-900 sm:col-span-2">{{ $staff->role }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-5 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-500">Account creation date</dt>
                    <dd class="text-sm text-slate-900 sm:col-span-2">{{ $staff->created_at?->format('d F Y, h:i A') }}</dd>
                </div>
            </dl>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6">
                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">
                    <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                    Attendance location
                </p>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Sign-in location</h2>
            </div>

            @if ($attendance && $officeLocation)
                @php
                    $isInsideOffice = $attendance->distance <= $officeLocation->allowed_radius;
                    $distanceVariation = abs($officeLocation->allowed_radius - $attendance->distance);
                @endphp

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Staff sign-in</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Latitude</dt><dd class="font-semibold text-slate-900">{{ number_format($attendance->latitude, 7) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Longitude</dt><dd class="font-semibold text-slate-900">{{ number_format($attendance->longitude, 7) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Check-in time</dt><dd class="font-semibold text-slate-900">{{ $attendance->check_in_time?->format('H:i:s') ?? 'Not available' }}</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Office location</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Latitude</dt><dd class="font-semibold text-slate-900">{{ number_format($officeLocation->latitude, 7) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Longitude</dt><dd class="font-semibold text-slate-900">{{ number_format($officeLocation->longitude, 7) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Allowed radius</dt><dd class="font-semibold text-slate-900">{{ number_format($officeLocation->allowed_radius, 0) }}m</dd></div>
                            </dl>
                        </div>
                    </div>

                    <div>
                        <div id="staffLocationMap" class="h-64 w-full rounded-xl"></div>
                        <div class="mt-4 rounded-xl {{ $isInsideOffice ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} p-4">
                            <p class="font-semibold">{{ $isInsideOffice ? 'Inside Office' : 'Outside Office' }}</p>
                            <p class="mt-1 text-sm">
                                Actual distance: {{ number_format($attendance->distance, 2) }}m
                                <span class="mx-1">&middot;</span>
                                {{ $isInsideOffice ? 'Remaining' : 'Exceeded by' }}: {{ number_format($distanceVariation, 2) }}m
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <p class="rounded-xl border border-slate-200 px-4 py-5 text-sm text-slate-500">
                    Location details are unavailable until this staff member has a recorded check-in and an office location is configured.
                </p>
            @endif
        </section>
    </main>

    @if ($attendance && $officeLocation)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const officeLocation = [{{ $officeLocation->latitude }}, {{ $officeLocation->longitude }}];
            const staffLocation = [{{ $attendance->latitude }}, {{ $attendance->longitude }}];
            const locationMap = L.map('staffLocationMap');

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(locationMap);

            L.marker(officeLocation).addTo(locationMap).bindPopup('Office location');
            L.marker(staffLocation).addTo(locationMap).bindPopup('Staff sign-in location');
            locationMap.fitBounds([officeLocation, staffLocation], { padding: [24, 24] });
        </script>
    @endif
</body>

</html>