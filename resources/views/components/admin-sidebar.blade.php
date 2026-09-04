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
                <a href="#dashboard" onclick="showDashboardView(); return false;" class="flex items-center gap-3 rounded-xl bg-cyan-50 px-3 py-3 text-sm font-semibold text-cyan-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h7V3H3v10Zm0 8h7v-5H3v5Zm11 0h7V11h-7v10Zm0-14h7V3h-7v4Z" /></svg>
                    Dashboard
                </a>
                <a href="#users" onclick="showUsersView(); return false;" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1m6-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-5v6m3-3h-6" /></svg>
                    Users
                </a>
                <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657 13.414 21a2 2 0 0 1-2.828 0l-4.243-4.343a8 8 0 1 1 11.314 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Distance settings
                </a>
                <a href="{{ route('admin.time-settings.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                    Time interval
                </a>
            </nav>
        </div>

        <div class="mt-8 hidden rounded-2xl bg-slate-950 p-4 text-white lg:block">
            <p class="text-xs font-semibold text-cyan-300">Daily pulse</p>
            <p class="mt-2 text-sm leading-5 text-slate-300">Keep your team arrival data current and visible.</p>
        </div>
    </div>
</aside>
