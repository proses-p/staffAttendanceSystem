<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit {{ $staff->name }} | Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-3xl p-4 sm:p-6 lg:p-10">
        <header class="mb-8 flex flex-col gap-4 border-b border-slate-200 pb-7 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">
                    <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                    Staff details
                </p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                    Edit staff member
                </h1>
                <p class="mt-2 text-sm text-slate-500">Update {{ $staff->name }}'s account information.</p>
            </div>

            <a href="{{ route('admin.dashboard', ['#' => 'users']) }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-800">
                Back to users
            </a>
        </header>

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('staff.update', $staff) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Full Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $staff->name) }}" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $staff->email) }}" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div>
                    <label for="organization" class="mb-2 block text-sm font-semibold text-slate-700">Organization</label>
                    <input type="text" id="organization" name="organization" value="{{ old('organization', $staff->organization) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                </div>

                <div class="border-t border-slate-200 pt-6">
                    <p class="mb-4 text-sm font-semibold text-slate-700">Change Password</p>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">New Password</label>
                            <input type="password" id="password" name="password" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100">
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">Leave both password fields blank to keep the current password.</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="brand-button rounded-xl px-5 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                        Update Staff Member
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>

</html>
