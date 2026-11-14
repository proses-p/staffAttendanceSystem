<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Operations · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')
        <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-[1600px] space-y-8">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Staff activity</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Field Operations</h1>
                    <p class="mt-2 text-sm text-slate-500">Current field visits and completed visit history.</p>
                </header>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold">Active Field Staff Now</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $activeVisits->count() }} active visits</p>
                        </div>
                    </div>
                    @forelse ($activeVisits as $visit)
                        @php
                            $mapUrl = $visit->last_lat !== null && $visit->last_lng !== null
                                ? 'https://google.com/maps/search/?api=1&query='.$visit->last_lat.','.$visit->last_lng
                                : ($visit->clock_in_lat !== null && $visit->clock_in_lng !== null
                                    ? 'https://google.com/maps/search/?api=1&query='.$visit->clock_in_lat.','.$visit->clock_in_lng
                                    : null);
                        @endphp
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                <div>
                                    <h3 class="font-semibold">{{ $visit->user?->name ?? 'Unknown staff member' }}</h3>
                                    <p class="mt-1 text-sm font-medium text-slate-700">{{ $visit->client_or_service_name ?: str_replace('_', ' ', $visit->task_type) }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $visit->purpose_description ?: 'Purpose not provided' }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Started {{ $visit->clock_in_at?->format('d M Y, H:i') ?? 'Unknown' }}</p>
                                </div>
                                @if ($mapUrl)
                                    <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex w-fit items-center gap-2 rounded-lg border border-cyan-200 px-3 py-2 text-sm font-semibold text-cyan-800 hover:bg-cyan-50">
                                        View Location on Google Maps
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                @else
                                    <span class="text-sm text-slate-400">Location unavailable</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                            No staff members are currently on an active field visit.
                        </div>
                    @endforelse
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5">
                        <h2 class="text-xl font-semibold">Completed Field Visits History</h2>
                        <p class="mt-1 text-sm text-slate-500">Latest 100 completed visits for this company.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1450px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Staff Name</th>
                                    <th class="px-4 py-3">Client / Service</th>
                                    <th class="px-4 py-3">Purpose</th>
                                    <th class="px-4 py-3">Start</th>
                                    <th class="px-4 py-3">End</th>
                                    <th class="px-4 py-3">Duration</th>
                                    <th class="px-4 py-3">Expense (TZS)</th>
                                    <th class="px-4 py-3">Receipt</th>
                                    <th class="px-4 py-3">Completion Notes</th>
                                    <th class="px-4 py-3">Maps</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($completedVisits as $item)
                                    @php
                                        $visit = $item['assignment'];
                                        $duration = $item['duration_minutes'];
                                        $startMap = $visit->clock_in_lat !== null && $visit->clock_in_lng !== null
                                            ? 'https://google.com/maps/search/?api=1&query='.$visit->clock_in_lat.','.$visit->clock_in_lng
                                            : null;
                                        $endMap = $visit->clock_out_lat !== null && $visit->clock_out_lng !== null
                                            ? 'https://google.com/maps/search/?api=1&query='.$visit->clock_out_lat.','.$visit->clock_out_lng
                                            : null;
                                    @endphp
                                    <tr class="align-top hover:bg-slate-50">
                                        <td class="px-4 py-4 font-semibold">{{ $visit->user?->name ?? 'Unknown staff member' }}</td>
                                        <td class="px-4 py-4">{{ $visit->client_or_service_name ?: str_replace('_', ' ', $visit->task_type) }}</td>
                                        <td class="max-w-xs whitespace-pre-wrap px-4 py-4 text-slate-600">{{ $visit->purpose_description ?: '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $visit->clock_in_at?->format('d M Y, H:i') }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $visit->clock_out_at?->format('d M Y, H:i') }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 font-medium">{{ intdiv($duration, 60) }}h {{ $duration % 60 }}m</td>
                                        <td class="whitespace-nowrap px-4 py-4 font-medium">{{ number_format($item['expense_amount'], 2) }}</td>
                                        <td class="px-4 py-4">
                                            @if ($item['receipt_url'])
                                                <a href="{{ $item['receipt_url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Open receipt photo">
                                                    <img src="{{ $item['receipt_url'] }}" alt="Receipt photo" class="h-14 w-14 rounded-lg border border-slate-200 object-cover">
                                                </a>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="max-w-xs whitespace-pre-wrap px-4 py-4 text-slate-600">{{ $visit->completion_notes ?: '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-4">
                                            @if ($startMap)<a class="font-semibold text-cyan-700 hover:underline" href="{{ $startMap }}" target="_blank" rel="noopener noreferrer">Start</a>@endif
                                            @if ($startMap && $endMap)<span class="px-1 text-slate-400">/</span>@endif
                                            @if ($endMap)<a class="font-semibold text-cyan-700 hover:underline" href="{{ $endMap }}" target="_blank" rel="noopener noreferrer">End</a>@endif
                                            @if (!$startMap && !$endMap)<span class="text-slate-400">—</span>@endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="px-4 py-12 text-center text-slate-500">No completed field visits are available yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
