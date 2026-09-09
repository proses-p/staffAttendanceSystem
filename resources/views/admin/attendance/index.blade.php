<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Daily Attendance Report</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-6 lg:p-10">

    <div class="mx-auto max-w-6xl">

        <header class="mb-8 flex flex-col gap-3 border-b border-slate-200 pb-7 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">
                    <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                    Operations report
                </p>

                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                    Daily attendance
                </h1>

                <p class="mt-2 text-sm text-slate-500">
                    {{ $today->format('l, d F Y') }}
                    <span class="mx-2 text-slate-300">/</span>
                    A clear view of today's arrivals.
                </p>
            </div>
            <span class="inline-flex w-fit items-center rounded-full bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700">
                {{ $staff->count() }} active staff
            </span>

        </header>


        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="overflow-x-auto">
            <table class="min-w-[680px] w-full text-left">

                <thead class="border-b border-slate-200 bg-slate-50/80">

                    <tr class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">

                        <th class="px-6 py-4">
                            Staff Name
                        </th>

                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Sign In</th>
                        <th class="px-6 py-4">Sign Out</th>
                        <th class="px-6 py-4">Work duration</th>
                        <th class="px-6 py-4">Attendance status</th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-slate-100">

                    @forelse ($staff as $member)

                        @php
                            $attendance = $member->attendances->first();
                        @endphp

                        <tr class="transition hover:bg-orange-50/40">

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-sm font-semibold text-orange-700">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </span>
                                    <span class="font-semibold text-slate-900">{{ $member->name }}</span>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $today->format('d M Y') }}
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $attendance?->check_in_time?->format('h:i A') ?? '—' }}
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $attendance?->check_out_time?->format('h:i A') ?? '—' }}
                            </td>

                            <td class="px-6 py-4 text-sm font-medium text-slate-600">
                                {{ $attendance?->work_duration ?? '—' }}
                            </td>


                            <td class="px-6 py-4">

                                @if ($attendance?->check_out_time)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-500"></span>
                                        Signed Out
                                    </span>
                                @elseif ($attendance)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        In Office
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        Not Signed In
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-sm text-slate-500"
                            >
                                No active staff found.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>
            </div>

        </div>

    </div>

</body>

</html>
