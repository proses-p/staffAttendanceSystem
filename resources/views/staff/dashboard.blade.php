<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Staff Attendance | Dudumizi</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-8">

    <div class="mx-auto w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_20px_55px_rgba(15,23,42,0.08)] sm:p-10">

        <div class="text-center">
            <div class="mb-8 flex justify-end">
                <details class="relative">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-100 text-sm font-semibold text-orange-700">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-xs font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                            <span class="block text-[11px] text-slate-500">Staff member</span>
                        </span>
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-2 text-left shadow-lg">
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
            </div>


            <div class="brand-mark mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-sm">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">StaffFlow / Attendance</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">
                Welcome, {{ auth()->user()->name }}!
            </h1>

            @if ($attendance)

                <div class="mt-6">

                    <p class="text-slate-500">
                        Your attendance has already been recorded today.
                    </p>

                    <div class="mt-6 grid gap-3 text-left sm:grid-cols-2">

                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                <strong class="block text-xs uppercase tracking-wide text-slate-400">Date</strong>
                            {{ $attendance->attendance_date->format('d F Y') }}
                        </p>

                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                <strong class="block text-xs uppercase tracking-wide text-slate-400">Day</strong>
                            {{ $attendance->attendance_date->format('l') }}
                        </p>

                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                <strong class="block text-xs uppercase tracking-wide text-slate-400">Time of Arrival</strong>
                            {{ \Carbon\Carbon::parse($attendance->check_in_time)->format('h:i A') }}
                        </p>

                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                <strong class="block text-xs uppercase tracking-wide text-slate-400">Status</strong>
                            {{ ucfirst($attendance->status) }}
                        </p>

                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                <strong class="block text-xs uppercase tracking-wide text-slate-400">Work duration</strong>
                            {{ $attendance->work_duration ?? '—' }}
                        </p>

                    </div>

                    <div
                        id="checkout-section"
                        class="mt-6"
                    >
                        @if ($attendance->check_out_time)
                            <div class="rounded-xl border border-green-200 bg-green-50 p-5 text-left text-green-700">
                                <h3 class="text-lg font-semibold">Signed Out ✓</h3>
                                <div class="mt-4 space-y-2 text-sm">
                                    <p><strong>Date:</strong> {{ $attendance->attendance_date->format('d F Y') }}</p>
                                    <p><strong>Day:</strong> {{ $attendance->attendance_date->format('l') }}</p>
                                    <p><strong>Time of Departure:</strong> {{ \Carbon\Carbon::parse($attendance->check_out_time)->format('h:i A') }}</p>
                                    <p><strong>Work duration:</strong> {{ $attendance->work_duration ?? '—' }}</p>
                                </div>
                            </div>
                        @else
                            <button
                                id="sign-out-button"
                                type="button"
                                class="brand-button w-full rounded-xl py-3 text-sm font-semibold text-white"
                            >
                                Sign Out
                            </button>
                            <div id="checkout-message" class="mt-4"></div>
                        @endif
                    </div>

                </div>

            @else

                <div
                    id="attendance-message"
                    class="mt-6 text-slate-600"
                >
                   <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-blue-700">
                        <div class="font-semibold">
                            Getting your location
                        </div>
                        <p class="text-sm mt-2">
                            Please wait while we verify your location.
                        </p>
                   </div>
                </div>

            @endif

        </div>



    </div>


    @if (!$attendance)

        <script>
    document.addEventListener('DOMContentLoaded', function () {

        const message = document.getElementById('attendance-message');

        function showMessage(type, title, description = '') {
            const styles = {
                error: 'bg-red-50 border-red-200 text-red-700',
                warning: 'bg-yellow-50 border-yellow-200 text-yellow-700',
                success: 'bg-green-50 border-green-200 text-green-700'
            };

            message.innerHTML = `
                <div class="border rounded-xl p-5 ${styles[type]}">
                    <h3 class="font-semibold text-lg">
                        ${title}
                    </h3>
                    ${
                        description
                            ? `<p class="mt-2 text-sm">${description}</p>`
                            : ''
                    }
                </div>
                `;
        }
        // if browser does not support gps
        if (!navigator.geolocation) {
           showMessage(
            'error',
            'Location Not Supported',
            'Your browser does not support location services.'
           );
            return;
        }
        message.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-blue-700">
                <div class="font-semibold">
                    Getting your location...
                </div>
                <p class="text-sm mt-2">
                    Please wait while we verify your location.
                </p>
            </div>
        `

        navigator.geolocation.getCurrentPosition(

            function (position) {

                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                const accuracy = position.coords.accuracy;

                fetch("{{ route('attendance.check-in', [], false) }}", {

                    method: "POST",

                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "application/json"
                    },

                    body: JSON.stringify({
                        latitude: latitude,
                        longitude: longitude,
                        accuracy: accuracy
                    })

                })
                .then(async response => {
                    const data = await response.json();
                    return {
                        status: response.status,
                        data: data
                    };
                })

                .then(result => {
                    const data = result.data;
                    // success message

                    if (data.success) {

                        const attendance = data.data;

                        message.innerHTML = `
                            <div class="bg-green-50 border border-green-200 rounded-xl p-5">
                                <h3 class="text-green-700 font-semibold text-lg">
                                    welcome, {{ auth()->user()->name }}
                                </h3>

                                <p class="text-green-600 font-semibold text-lg">
                                    ${data.message}
                                </p>

                                <div class="mt-6 space-y-3 text-left">

                                    <p>
                                        <strong>Date:</strong>
                                        ${attendance.date}
                                    </p>

                                    <p>
                                        <strong>Day:</strong>
                                        ${attendance.day}
                                    </p>

                                    <p>
                                        <strong>Time of Arrival:</strong>
                                        ${attendance.arrival_time}
                                    </p>

                                    <p>
                                        <strong>Status:</strong>
                                        ${attendance.status}
                                    </p>

                                </div>

                                <div id="checkout-section" class="mt-6">
                                    <button
                                        id="sign-out-button"
                                        type="button"
                                        class="brand-button w-full rounded-xl py-3 text-sm font-semibold text-white"
                                    >
                                        Sign Out
                                    </button>
                                    <div id="checkout-message" class="mt-4"></div>
                                </div>

                            </div>
                        `;
                        renderCheckout(attendance);
                        return;

                    }
                    // if already signed in
                    if (result.status === 409) {
                        showMessage(
                            'warning',
                            'Already Signed In',
                            data.message
                        );
                        return;
                    }

                    // OUtside office
                    if (result.status === 403) {
                        const distance = data.distance
                            ? `${data.distance} meters`
                            : '';
                        showMessage(
                            'error',
                            'You are outside the office',
                            `${data.message} ${distance}`
                        );
                        return;
                    }

                    // server validation errors
                    showMessage(
                        'error',
                        'Unable to record attendance',
                        data.message ?? 'Something went wrong.'
                    );

                })

                .catch(error => {

                    console.error(error);

                    showMessage(
                        'error',
                        'Connection Error',
                        'Unable to connect to the server. Please tyr again.'
                    );

                });

            },

            function (error) {

               switch (error.code) {
                    case error.PERMISSION_DENIED:
                        showMessage(
                            'warning',
                            'Location permission required',
                            'Please allow location access in your browser to record attendance.'
                        );
                        break;
                    case error.POSITION_UNAVAILABLE:
                        showMessage(
                            'error',
                            'Location unavailable',
                            'Unable to determine your current location.'
                        );
                        break;
                    case error.TIMEOUT:
                        showMessage(
                            'warning',
                            'Location request timed out',
                            'Unable to get your location. Please try again.'
                        );
                        break;
                    default:
                        showMessage(
                            'error',
                            'Location error',
                            'Unable to determine your location.'
                        )
               }
            },

            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );

    });

    // the location monitor script
    document.addEventListener('DOMContentLoaded', function () {
        if (!navigator.geolocation) {
            console.log('Geolocation is not supported by this browser');
            return;
        }
        function updateStaffLocation() {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    fetch("{{ route('staff.location.update', [], false) }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}",
                            "Accept": "application/json"
                        },
                        body: JSON.stringify({
                            latitude: latitude,
                            longitude: longitude,
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Staff location updated:', data);

                    })
                    .catch(error => {
                        console.error('Location update failed:', error)
                    });
                },
                function (error) {
                    console.log('Unable to update staff location:', error.message);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                }
                );


        }
        // get location immediately
        updateStaffLocation();
        // update location every 60 meters
        setInterval(() => {
            updateStaffLocation();
        }, 60000);
    });
</script>

    @endif

    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('#sign-out-button');

            if (!button || button.disabled) {
                return;
            }

            const message = document.getElementById('checkout-message');

            if (!navigator.geolocation) {
                showCheckoutMessage('error', 'Location Not Supported', 'Your browser does not support location services.');
                return;
            }

            button.disabled = true;
            button.textContent = 'Getting location...';
            showCheckoutMessage('info', 'Getting your location...', 'Please wait while we verify your location.');

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    fetch("{{ route('attendance.check-out', [], false) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        })
                    })
                    .then(async response => ({
                        status: response.status,
                        data: await response.json()
                    }))
                    .then(result => {
                        if (result.data.success) {
                            renderCheckout(result.data.data);
                            return;
                        }

                        button.disabled = false;
                        button.textContent = 'Sign Out';
                        showCheckoutMessage(
                            result.status === 403 ? 'error' : 'warning',
                            result.status === 409 ? 'Already Signed Out' : 'Unable to sign out',
                            result.data.message ?? 'Something went wrong.'
                        );
                    })
                    .catch(error => {
                        console.error(error);
                        button.disabled = false;
                        button.textContent = 'Sign Out';
                        showCheckoutMessage('error', 'Connection Error', 'Unable to connect to the server. Please try again.');
                    });
                },
                function (error) {
                    button.disabled = false;
                    button.textContent = 'Sign Out';

                    const locationErrors = {
                        [error.PERMISSION_DENIED]: ['Location permission required', 'Please allow location access in your browser to sign out.'],
                        [error.POSITION_UNAVAILABLE]: ['Location unavailable', 'Unable to determine your current location.'],
                        [error.TIMEOUT]: ['Location request timed out', 'Unable to get your location. Please try again.']
                    };
                    const [title, description] = locationErrors[error.code] ?? ['Location error', 'Unable to determine your location.'];
                    showCheckoutMessage('warning', title, description);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });

        function showCheckoutMessage(type, title, description) {
            const message = document.getElementById('checkout-message');

            if (!message) {
                return;
            }

            const styles = {
                error: 'bg-red-50 border-red-200 text-red-700',
                warning: 'bg-yellow-50 border-yellow-200 text-yellow-700',
                info: 'bg-blue-50 border-blue-200 text-blue-700'
            };

            message.innerHTML = `
                <div class="border rounded-xl p-5 ${styles[type]}">
                    <h3 class="font-semibold">${title}</h3>
                    <p class="mt-2 text-sm">${description}</p>
                </div>
            `;
        }

        function renderCheckout(attendance) {
            const section = document.getElementById('checkout-section');

            if (!section) {
                return;
            }

            section.innerHTML = `
                <div class="rounded-xl border border-green-200 bg-green-50 p-5 text-left text-green-700">
                    <h3 class="text-lg font-semibold">Signed Out ✓</h3>
                    <div class="mt-4 space-y-2 text-sm">
                        <p><strong>Date:</strong> ${attendance.date}</p>
                        <p><strong>Day:</strong> ${attendance.day}</p>
                        <p><strong>Time of Departure:</strong> ${attendance.departure_time}</p>
                        <p><strong>Work duration:</strong> ${attendance.work_duration ?? '—'}</p>
                    </div>
                </div>
            `;
        }
    </script>

</body>

</html>
