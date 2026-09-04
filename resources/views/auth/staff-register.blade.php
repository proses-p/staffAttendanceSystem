<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Registration | Staff Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 px-4 py-10 text-slate-900 sm:py-16">
    <main class="mx-auto w-full max-w-lg">
        <div class="mb-8 text-center">
            <div class="brand-mark mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1m6-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-5v6m3-3h-6" />
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">StaffFlow</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Complete your registration</h1>
            <p class="mt-2 text-sm text-slate-500">Set up your staff account to access the attendance workspace.</p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_16px_40px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">
                You have been invited to join the staff attendance system.
            </div>

            <form action="{{ route('staff.register', ['token' => $invitation->token]) }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                    <input id="email" type="email" name="email" value="{{ $invitation->email }}" readonly class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 outline-none">
                </div>

                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-700">Full name</label>
                    <input id="name" type="text" name="name" placeholder="Enter your full name" autocomplete="name" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100">
                </div>

                <div>
                    <label for="organization" class="mb-2 block text-sm font-medium text-slate-700">Organization</label>
                    <select id="organization" name="organization" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100">
                        <option value="" disabled selected>Select your organization</option>
                        <option value="Dudumizi">Dudumizi</option>
                        <option value="Tanzmed">Tanzmed</option>
                    </select>
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" type="password" name="password" placeholder="Create a password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100">
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">Password confirmation</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat your password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100">
                </div>

                <button type="submit" class="brand-button w-full rounded-xl px-4 py-3 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">Complete registration</button>
            </form>
        </section>
    </main>
</body>

</html>
