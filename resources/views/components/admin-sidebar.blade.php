<aside class="w-full shrink-0 border-b border-slate-200 bg-white lg:min-h-screen lg:w-64 lg:border-b-0 lg:border-r">
    <div class="flex h-full flex-col p-5 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-cyan-400 shadow-sm">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M3 12h18M5.64 5.64l12.72 12.72M18.36 5.64 5.64 18.36" /></svg>
            </div>
            <div>
                <p class="text-sm font-semibold tracking-tight text-slate-950">StaffFlow</p>
                <p class="text-xs text-slate-500">Admin workspace</p>
            </div>
        </div>

        <div class="mt-10">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">Workspace</p>
            <nav class="space-y-1.5">
                <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) onclick="showDashboardView(); return false;" @endif class="flex items-center gap-3 rounded-xl bg-cyan-50 px-3 py-3 text-sm font-semibold text-cyan-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h7V3H3v10Zm0 8h7v-5H3v5Zm11 0h7V11h-7v10Zm0-14h7V3h-7v4Z" /></svg>
                    Dashboard
                </a>
                <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) onclick="showUsersView(); return false;" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1m6-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-5v6m3-3h-6" /></svg>
                    Users
                </a>
                <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) onclick="showDistanceView(); return false;" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657 13.414 21a2 2 0 0 1-2.828 0l-4.243-4.343a8 8 0 1 1 11.314 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Distance 
                </a>
                <a href="{{ route('admin.time-settings.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                    Time interval
                </a>

                <!-- logout component -->
                 <details class="relative">
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-950 text-sm font-semibold text-cyan-300">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block text-xs font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                                    <span class="block text-[11px] text-slate-500">Administrator</span>
                                </span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 z-20 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                <div class="border-b border-slate-100 px-3 py-2">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout', [], false) }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4M13 5V3h8v18h-8v-2" />
                                        </svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </details>

            </nav>
        </div>

        <div class="mt-auto hidden rounded-2xl bg-slate-950 p-4 text-white lg:block">
            <p class="text-xs font-semibold text-cyan-300">Daily pulse</p>
            <p class="mt-2 text-sm leading-5 text-slate-300">Keep your team arrival data current and visible.</p>
        </div>
    </div>
</aside>
