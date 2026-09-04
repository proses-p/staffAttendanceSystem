<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Time Interval Settings</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

    <div class="min-h-screen flex flex-col lg:flex-row">

        @include('components.admin-sidebar')

        <main class="flex-1 p-4 sm:p-6 lg:p-10">

            <div class="mx-auto max-w-3xl">

                <div class="mb-8 border-b border-slate-200 pb-7">
                    <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">
                        <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                        Workspace preferences
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                        Time interval
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Set the expected arrival time for staff arrivals.
                    </p>
                </div>

                @if (session('success'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">

                    <form method="POST"
                          action="{{ route('admin.time-settings.store') }}">

                        @csrf

                        <div>
                            <label
                                for="expected_arrival_time"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Expected Arrival Time
                            </label>

                            <input
                                type="time"
                                id="expected_arrival_time"
                                name="expected_arrival_time"
                                value="{{ $setting?->expected_arrival_time?->format('H:i') }}"
                                required
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100"
                            >

                            <p class="mt-3 text-sm leading-6 text-slate-500">
                                Staff who sign in at or before this time will be marked as
                                <span class="font-semibold text-emerald-600">On Time</span>.
                                Staff who sign in after this time will be marked as
                                <span class="font-semibold text-amber-600">Late</span>.
                            </p>
                        </div>

                        <div class="mt-6 flex justify-end">

                            <button
                                type="submit"
                                class="brand-button rounded-xl px-5 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5"
                            >
                                Save Time Setting
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </main>

    </div>

</body>

</html>
