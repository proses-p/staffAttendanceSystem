
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>Login | Staff Attendance</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center px-4">

    <div class="w-full max-w-md">

        <!-- Logo / Header -->
        <div class="text-center mb-8">

            <div class="mx-auto mb-4 w-14 h-14 rounded-2xl bg-orange-600
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
                                    d="M16 12H8m8-4H8m12 4a8 8 0 11-16 0 8 8 0 0116 0z"
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
                            class="w-full pl-10 pr-4 py-3 rounded-lg
                                   border border-gray-300
                                   text-gray-900 placeholder-gray-400
                                   outline-none transition
                                   focus:border-orange-500
                                   focus:ring-2 focus:ring-orange-100"
                        >

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
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-5a2 2 0 00-2-2H6a2 2 0 00-2 2v5a2 2 0 002 2h12a2 2 0 002-2v-5a2 2 0 00-2-2zm-2-9V7a4 4 0 00-8 0v3h8z"
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
                           bg-orange-600 hover:bg-orange-700
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

</body>
</html>
