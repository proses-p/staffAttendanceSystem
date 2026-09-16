
<aside class="w-full shrink-0 border-b border-slate-200 bg-white lg:min-h-screen lg:w-64 lg:border-b-0 lg:border-r">

    <div class="flex h-full flex-col p-5 lg:p-6">

        <!-- =========================
             BRAND
        ========================== -->
        <div class="flex items-center gap-3">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-cyan-400 shadow-sm">
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.75"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 3v18M3 12h18M5.64 5.64l12.72 12.72M18.36 5.64 5.64 18.36"
                    />
                </svg>
            </div>

            <div>
                <p class="text-sm font-semibold tracking-tight text-slate-950">
                    Staff Flow
                </p>

                <p class="text-xs text-slate-500">
                    Admin workspace
                </p>
            </div>

        </div>


        <!-- =========================
             NAVIGATION
        ========================== -->
        <div class="mt-10">

            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                Workspace
            </p>

            <nav class="space-y-1.5">

                <!-- Dashboard -->
                <a
                    href="{{ route('admin.dashboard') }}"
                    @if (request()->routeIs('admin.dashboard'))
                        onclick="showDashboardView(); return false;"
                    @endif
                    class="flex items-center gap-3 rounded-xl bg-cyan-50 px-3 py-3 text-sm font-semibold text-cyan-700 transition hover:bg-cyan-100"
                >

                    <svg
                        class="h-5 w-5 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 13h7V3H3v10Zm0 8h7v-5H3v5Zm11 0h7V11h-7v10Zm0-14h7V3h-7v4Z"
                        />
                    </svg>

                    <span>Dashboard</span>

                </a>


                <!-- Users -->
                <a
                    href="{{ route('admin.dashboard') }}"
                    @if (request()->routeIs('admin.dashboard'))
                        onclick="showUsersView(); return false;"
                    @endif
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                >

                    <svg
                        class="h-5 w-5 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1m6-9a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm7-5v6m3-3h-6"
                        />
                    </svg>

                    <span>Users</span>

                </a>


                <!-- Distance -->
                <a
                    href="{{ route('admin.dashboard') }}"
                    @if (request()->routeIs('admin.dashboard'))
                        onclick="showDistanceView(); return false;"
                    @endif
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                >

                    <svg
                        class="h-5 w-5 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17.657 16.657 13.414 21a2 2 0 0 1-2.828 0l-4.243-4.343a8 8 0 1 1 11.314 0Z"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                        />
                    </svg>

                    <span>Distance</span>

                </a>


                <!-- Time Interval -->
                <a
                    href="{{ route('admin.time-settings.index') }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                >

                    <svg
                        class="h-5 w-5 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="9"
                        />

                        <path
                            stroke-linecap="round"
                            d="M12 7v5l3 2"
                        />
                    </svg>

                    <span>Time interval</span>

                </a>


                <!-- =========================
                     ATTENDANCE REPORT
                ========================== -->

                <button
                    type="button"
                    onclick="openAttendanceReportModal()"
                    class="group flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                >

                    <span class="flex h-5 w-5 shrink-0 items-center justify-center">

                        <svg
                            class="h-5 w-5 transition group-hover:text-cyan-600"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 14h6m-6 4h6M9 10h6m2 11H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6l5 5v13a2 2 0 0 1-2 2Z"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 3v5h5"
                            />
                        </svg>

                    </span>

                    <span class="flex-1">
                         Reports
                    </span>

                    <svg
                        class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-cyan-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m9 18 6-6-6-6"
                        />
                    </svg>

                </button>

            </nav>

        </div>


        <!-- =========================
             BOTTOM SECTION
        ========================== -->

        <div class="mt-auto space-y-4 pt-8">


            <!-- Daily Pulse -->
            <div class="hidden rounded-2xl bg-slate-950 p-4 text-white lg:block">

                <div class="flex items-center gap-2">

                    <span class="h-2 w-2 rounded-full bg-cyan-400"></span>

                    <p class="text-xs font-semibold text-cyan-300">
                        Daily pulse
                    </p>

                </div>

                <p class="mt-2 text-sm leading-5 text-slate-300">
                    Keep your team arrival data current and visible.
                </p>

            </div>


            <!-- =========================
                 ADMIN PROFILE / LOGOUT
            ========================== -->

            <details class="relative">

                <summary
                    class="flex cursor-pointer list-none items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
                >

                    <!-- Avatar -->
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-950 text-sm font-semibold text-cyan-300">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>


                    <!-- User Info -->
                    <span class="min-w-0 flex-1 text-left">

                        <span class="block truncate text-xs font-semibold text-slate-900">
                            {{ auth()->user()->name }}
                        </span>

                        <span class="block text-[11px] text-slate-500">
                            Administrator
                        </span>

                    </span>


                    <!-- Arrow -->
                    <svg
                        class="h-4 w-4 shrink-0 text-slate-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m6 9 6 6 6-6"
                        />
                    </svg>

                </summary>


                <!-- Dropdown -->
                <div class="absolute bottom-full left-0 z-30 mb-2 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-xl">

                    <div class="border-b border-slate-100 px-3 py-2">

                        <p class="truncate text-sm font-semibold text-slate-900">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="truncate text-xs text-slate-500">
                            {{ auth()->user()->email }}
                        </p>

                    </div>


                    <!-- Logout -->
                    <form
                        method="POST"
                        action="{{ route('logout', [], false) }}"
                        class="mt-1"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                        >

                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.75"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12H3m0 0 4-4m-4 4 4 4M13 5V3h8v18h-8v-2"
                                />
                            </svg>

                            Logout

                        </button>

                    </form>

                </div>

            </details>

        </div>

    </div>

    </aside>


    <!-- =====================================================
        ATTENDANCE REPORT MODAL
    ====================================================== -->

    <div
        id="attendanceReportModal"
        class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm sm:px-6"
    >

        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="attendanceReportTitle"
            class="w-full max-w-lg overflow-hidden rounded-3xl border border-white/70 bg-white shadow-[0_24px_70px_rgba(15,23,42,0.2)]"
        >

            <!-- Modal Header -->
            <div class="relative overflow-hidden border-b border-slate-100 bg-gradient-to-br from-[#fff8f5] via-white to-white p-6 sm:p-7">
                <div class="pointer-events-none absolute -right-12 -top-16 h-40 w-40 rounded-full border-[18px] border-[#f9e1d7]/70"></div>
                <div class="relative flex items-start justify-between gap-4">

                    <div class="flex items-start gap-4">

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#fff0eb] text-cyan-600 shadow-sm ring-1 ring-[#f8d8cd]">

                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 14h6m-6 4h6M9 10h6m2 11H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6l5 5v13a2 2 0 0 1-2 2Z"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 3v5h5"
                            />

                        </svg>

                        </div>

                        <div>

                        <h2 id="attendanceReportTitle" class="text-xl font-bold tracking-tight text-slate-950">
                            Attendance Report
                        </h2>

                        <p class="mt-1.5 max-w-sm text-sm leading-6 text-slate-500">
                            Choose a date range to generate a PDF summary of staff attendance.
                        </p>

                        </div>
                    </div>


                    <!-- Close -->
                    <button
                        type="button"
                        onclick="closeAttendanceReportModal()"
                        aria-label="Close attendance report"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-white hover:text-slate-700 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
                    >

                    <svg
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6l12 12M18 6 6 18"
                        />
                    </svg>

                    </button>

                </div>
            </div>


            <!-- Modal Form -->
            <form
                action="{{ route('admin.attendance.report') }}"
                method="GET"
                class="space-y-6 p-6 sm:p-7"
            >

                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- From -->
                    <div>

                        <label
                            for="report_from"
                            class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500"
                        >
                            From date
                        </label>

                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2" /><path stroke-linecap="round" d="M16 2v4M8 2v4m-5 4h18" /></svg>
                            <input
                                id="report_from"
                                type="date"
                                name="from"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-3 pl-10 text-sm font-medium text-slate-700 outline-none transition hover:border-slate-300 focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-[#f8d8cd]"
                            >
                        </div>

                    </div>


                    <!-- To -->
                    <div>

                        <label
                            for="report_to"
                            class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500"
                        >
                            To date
                        </label>

                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2" /><path stroke-linecap="round" d="M16 2v4M8 2v4m-5 4h18" /></svg>
                            <input
                                id="report_to"
                                type="date"
                                name="to"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-3 pl-10 text-sm font-medium text-slate-700 outline-none transition hover:border-slate-300 focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-[#f8d8cd]"
                            >
                        </div>

                    </div>
                </div>


                <!-- Actions -->
                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row">

                    <button
                        type="button"
                        onclick="closeAttendanceReportModal()"
                        class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-3 text-sm font-bold text-white shadow-[0_8px_18px_rgba(226,66,55,0.2)] transition hover:bg-cyan-700 hover:shadow-[0_10px_22px_rgba(226,66,55,0.28)] focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
                    >

                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"
                            />
                        </svg>

                        Download PDF

                    </button>

                </div>

            </form>

        </div>

        </div>

    <!-- =========================================================
     ATTENDANCE REPORT MODAL SCRIPT
========================================================= -->

<script>
    function openAttendanceReportModal() {
        const modal = document.getElementById('attendanceReportModal');

        if (!modal) return;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');
    }

    function closeAttendanceReportModal() {
        const modal = document.getElementById('attendanceReportModal');

        if (!modal) return;

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');
    }

    // Close when clicking outside the modal
    document.addEventListener('click', function (event) {

        const modal = document.getElementById('attendanceReportModal');

        if (!modal) return;

        if (event.target === modal) {
            closeAttendanceReportModal();
        }

    });

    // Close with Escape key
    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {
            closeAttendanceReportModal();
        }

    });
</script>
