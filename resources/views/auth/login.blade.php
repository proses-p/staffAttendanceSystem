
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>Login | Staff Attendance</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center px-4">

    <div class="w-full max-w-md">

        <!-- Logo / Header -->
        <div class="text-center mb-8">

            <div class="mx-auto mb-4 w-14 h-14 rounded-2xl bg-orange-400
                        flex items-center justify-center shadow-lg">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="w-7 h-7 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>

            </div>

            <h1 class="text-3xl font-bold text-gray-900">
                Staff Attendance
            </h1>

            <p class="mt-2 text-sm text-gray-500">
                Sign in to access your attendance dashboard
            </p>

        </div>


        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-7 sm:p-8">

            <!-- Session Error -->
            @if(session('error'))

                <div class="mb-5 rounded-lg bg-red-50 border border-red-200
                            px-4 py-3 text-sm text-red-700">

                    {{ session('error') }}

                </div>

            @endif

            @if(session('status'))
                <div class="mb-5 rounded-lg bg-green-50 border border-green-200
                            px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif


            <!-- Login Form -->
            <form
                method="POST"
                action="{{ route('login') }}"
                id="loginForm"
            >

                @csrf


                <!-- Email -->
                <div class="mb-5">

                    <label
                        for="email"
                        class="block text-sm font-medium text-gray-700 mb-2"
                    >
                        Email address
                    </label>

                    <div class="relative">

                        <div class="absolute inset-y-0 left-0 pl-3
                                    flex items-center pointer-events-none">

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-gray-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>

                        </div>

                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            class="w-full pl-10 pr-11 py-3 rounded-lg
                                   border border-gray-300
                                   text-gray-900 placeholder-gray-400
                                   outline-none transition
                                   focus:border-orange-500
                                   focus:ring-2 focus:ring-orange-100"
                        >

                        <button
                            type="button"
                            data-password-toggle="password"
                            aria-label="Show password"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-slate-700"
                        >
                            <svg data-eye class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6.75 9.75-6.75S21.75 12 21.75 12s-3.5 6.75-9.75 6.75S2.25 12 2.25 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-slash class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.58 10.59a2 2 0 0 0 2.83 2.83M9.88 5.09A10.7 10.7 0 0 1 12 4.88c6.25 0 9.75 7.12 9.75 7.12a16.7 16.7 0 0 1-3.03 3.93M6.18 6.18C3.68 7.88 2.25 12 2.25 12s3.5 7.12 9.75 7.12a10.7 10.7 0 0 0 4.12-.82" />
                            </svg>
                        </button>

                    </div>

                    @error('email')

                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>

                    @enderror

                </div>


                <!-- Password -->
                <div class="mb-6">

                    <label
                        for="password"
                        class="block text-sm font-medium text-gray-700 mb-2"
                    >
                        Password
                    </label>

                    <div class="relative">

                        <div class="absolute inset-y-0 left-0 pl-3
                                    flex items-center pointer-events-none">

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-5 h-5 text-gray-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z"
                                />
                            </svg>

                        </div>

                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                            class="w-full pl-10 pr-4 py-3 rounded-lg
                                   border border-gray-300
                                   text-gray-900 placeholder-gray-400
                                   outline-none transition
                                   focus:border-orange-500
                                   focus:ring-2 focus:ring-orange-100"
                        >

                    </div>

                    @error('password')

                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>

                    @enderror

                </div>


                <!-- Submit -->
                <button
                    type="submit"
                    id="loginButton"
                    class="w-full py-3 px-4 rounded-lg
                           bg-orange-400 hover:bg-orange-700
                           active:bg-orange-800
                           text-white font-semibold
                           shadow-sm transition duration-200
                           focus:outline-none focus:ring-2
                           focus:ring-orange-500 focus:ring-offset-2"
                >
                    Sign in
                </button>

            </form>

        </div>


        <!-- Footer -->
        <p class="text-center text-xs text-gray-400 mt-6">
            Staff Attendance Management System
        </p>

    </div>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                const isVisible = input.type === 'text';

                input.type = isVisible ? 'password' : 'text';
                button.querySelector('[data-eye]').classList.toggle('hidden', !isVisible);
                button.querySelector('[data-eye-slash]').classList.toggle('hidden', isVisible);
                button.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            });
        });
    </script>
</body>
</html>
