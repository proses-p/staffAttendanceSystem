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
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-lg bg-white rounded-2xl shadow-lg p-8">

        <div class="text-center">

            <h1 class="text-2xl font-bold text-gray-800">
                Welcome, {{ auth()->user()->name }}!
            </h1>

            @if ($attendance)

                <div class="mt-6">

                    <p class="text-gray-600">
                        Your attendance has already been recorded today.
                    </p>

                    <div class="mt-6 space-y-3 text-left">

                        <p>
                            <strong>Date:</strong>
                            {{ $attendance->attendance_date->format('d F Y') }}
                        </p>

                        <p>
                            <strong>Day:</strong>
                            {{ $attendance->attendance_date->format('l') }}
                        </p>

                        <p>
                            <strong>Time of Arrival:</strong>
                            {{ \Carbon\Carbon::parse($attendance->check_in_time)->format('h:i A') }}
                        </p>

                        <p>
                            <strong>Status:</strong>
                            {{ ucfirst($attendance->status) }}
                        </p>

                    </div>

                </div>

            @else

                <div
                    id="attendance-message"
                    class="mt-6 text-gray-600"
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


        <form
            action="{{ route('logout', [], false) }}"
            method="POST"
            class="mt-8"
        >

            @csrf

            <button
                type="submit"
                class="w-full bg-red-500 text-white py-3 rounded-xl"
            >
                Logout
            </button>

        </form>

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

                            </div>
                        `;
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

</body>

</html>
