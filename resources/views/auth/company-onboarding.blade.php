<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Administrator Onboarding | Staff Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 px-4 py-10 text-slate-900 sm:py-16">
    <main class="mx-auto w-full max-w-lg">
        <header class="mb-8 text-center">
            <div class="brand-mark mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18m6 0V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01" />
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">StaffFlow</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Set up your company</h1>
            <p class="mt-2 text-sm text-slate-500">Complete your company and administrator profile to get started.</p>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_16px_40px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="mb-6 rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">
                You were invited to become the administrator for this organization.
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('company.onboarding', ['token' => $invitation->token]) }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label for="company_name" class="mb-2 block text-sm font-medium text-slate-700">Company / Organization Name</label>
                    <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" maxlength="255" autocomplete="organization" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-700">Admin Full Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email Address</label>
                    <input id="email" type="email" value="{{ $invitation->email }}" readonly aria-readonly="true" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 outline-none">
                </div>

                <div>
                    <label for="phone" class="mb-2 block text-sm font-medium text-slate-700">Phone Number <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <button type="submit" class="brand-button w-full rounded-xl px-4 py-3 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">Create admin account</button>
            </form>
        </section>
    </main>
</body>
</html>
