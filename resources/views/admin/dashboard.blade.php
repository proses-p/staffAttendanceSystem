<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Daily Attendance Report</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-6">

    <div class="max-w-6xl mx-auto">

        <div class="mb-6 flex items-center justify-between">
            <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Daily Attendance Report
            </h1>

            <p class="text-gray-500 mt-1">
                {{ $today->format('l, d F Y') }}
            </p>
            </div>


            <div class="flex gap-3">
                <button
                type="button"
                onclick="openStaffModal()"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg"
            >
                + add staff
            </button>
                <button
                    type="button"
                    onclick="openLocationModal()"
                    class="bg-gray-800 text-white px-5 py-2 rounded-lg"
                >
                    Save office location
                </button>
            </div>

        </div>


        <div class="bg-white rounded-xl shadow overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="text-left p-4">
                            Staff Name
                        </th>

                        <th class="text-left p-4">
                            Email
                        </th>

                        <th class="text-left p-4">
                            Check In
                        </th>

                        <th class="text-left p-4">
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($staff as $member)

                        @php
                            $attendance = $member->attendances->first();
                        @endphp

                        <tr class="border-t">

                            <td class="p-4 font-medium">
                                {{ $member->name }}
                            </td>

                            <td class="p-4">
                                {{ $member->email }}
                            </td>

                            <td class="p-4">

                                @if ($attendance)

                                    {{ \Carbon\Carbon::parse(
                                        $attendance->check_in_time
                                    )->format('h:i A') }}

                                @else

                                    —

                                @endif

                            </td>


                            <td class="p-4">

                                @if ($attendance)

                                    <span class="px-3 py-1 rounded-full
                                        bg-green-100 text-green-700">
                                        Signed in
                                    </span>

                                @else

                                    <span class="px-3 py-1 rounded-full
                                        bg-red-100 text-red-700">
                                        Not Signed In
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                class="p-8 text-center text-gray-500"
                            >
                                No active staff found.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- modal --}}

    <div
    id="staffModal"
    class="hidden fixed inset-0 bg-black/50 items-center justify-center z-50 p-4"
>
    <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-6">

        <div class="flex justify-between items-center mb-5">

            <h2 class="text-xl font-bold">
                Register Staff
            </h2>

            <button
                type="button"
                onclick="closeStaffModal()"
                class="text-gray-500 text-xl"
            >
                &times;
            </button>

        </div>


        <div
            id="staffFormMessage"
            class="hidden mb-4"
        ></div>


        <form id="staffForm">

            @csrf

            <div class="space-y-4">

                <input
                    type="text"
                    name="name"
                    placeholder="Full Name"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >

                <input
                    type="email"
                    name="email"
                    placeholder="Email"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >

                <input
                    type="text"
                    name="phone"
                    placeholder="Phone Number"
                    class="w-full border rounded-lg px-3 py-2"
                >

                <input
                    type="text"
                    name="employee_id"
                    placeholder="Employee ID (Optional)"
                    class="w-full border rounded-lg px-3 py-2"
                >

                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >

                <input
                    type="password"
                    name="password_confirmation"
                    placeholder="Confirm Password"
                    class="w-full border rounded-lg px-3 py-2"
                    required
                >

            </div>


            <button
                type="submit"
                id="registerStaffBtn"
                class="w-full bg-blue-600 text-white py-2.5 rounded-lg mt-5"
            >
                Register Staff
            </button>

        </form>

    </div>
</div>

<!-- Office Location Modal -->
<div
    id="locationModal"
    class="hidden fixed inset-0 z-50 bg-black/50
           items-center justify-center px-4"
>
    <div class="bg-white w-full max-w-sm rounded-xl shadow-xl p-6">

        <h2 class="text-lg font-semibold text-gray-900">
            Save Office Location
        </h2>

        <p class="text-sm text-gray-500 mt-2">
            Make sure you are around the office before saving
            the office location.
        </p>

        <div class="flex justify-end gap-3 mt-6">

            <button
                type="button"
                onclick="closeLocationModal()"
                class="px-4 py-2 rounded-lg border
                       border-gray-300 text-gray-700"
            >
                Cancel
            </button>

            <button
                type="button"
                onclick="getOfficeLocation()"
                id="saveLocationButton"
                class="px-4 py-2 rounded-lg
                       bg-blue-700 text-white"
            >
                Save
            </button>

        </div>

    </div>
</div>

 {{-- script --}}
<script>
    function openLocationModal() {
        const modal = document.getElementById('locationModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeLocationModal() {
        const modal = document.getElementById('locationModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function openStaffModal() {
        const modal = document.getElementById('staffModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeStaffModal() {
        const modal = document.getElementById('staffModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function getOfficeLocation() {
        const button = document.getElementById('saveLocationButton');
        button.disabled = true;
        button.innerText = 'Getting location...';
        if (!navigator.geolocation) {
            alert('Location service are not supported by this browser');
            button.disabled = false;
            button.innerText = 'Save';
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position){
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;

                console.log('office latitude:', latitude);
                console.log('office longitude:', longitude);
                console.log('accuracy:', position.coords.accuracy);

                button.innerText = 'Saving...';

                fetch("{{ route('admin.office-location.store', [], false) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        latitude: latitude,
                        longitude: longitude,
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        closeLocationModal();
                    } else {
                        alert('Unable to save office location.');
                    }

                    button.disabled = false;
                    button.innerText = 'Save';
                })
                .catch(error => {
                    console.error(error);
                    alert('Something went wrong while saving the location.');
                    button.disabled = false;
                    button.innerText = 'Save';
                })
            },

            function(error) {
                console.log(error);
                alert('Unable to get your current location.');
                button.disabled = false;
                button.innerText = 'Save';
            },

            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    }


    document.getElementById('staffForm').addEventListener('submit', async function (event) {

        event.preventDefault();

        const form = this;
        const button = document.getElementById('registerStaffBtn');
        const message = document.getElementById('staffFormMessage');

        button.disabled = true;
        button.innerText = 'Registering...';

        const formData = new FormData(form);

        try {

            const response = await fetch(
                "{{ route('staff.store') }}",
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json'
                    },
                    body: formData
                }
            );

            const data = await response.json();

            if (response.ok && data.success) {

                message.className =
                    'mb-4 p-3 rounded-lg bg-green-100 text-green-700';

                message.innerText = data.message;

                form.reset();

                setTimeout(() => {
                    closeStaffModal();
                    window.location.reload();
                }, 800);

                return;
            }


        if (response.status === 422) {

            const errors = Object.values(data.errors || {})
                .flat()
                .join(' ');

            message.className =
                'mb-4 p-3 rounded-lg bg-red-100 text-red-700';

            message.innerText =
                errors || 'Please check the information provided.';

            return;
        }


        message.className =
            'mb-4 p-3 rounded-lg bg-red-100 text-red-700';

        message.innerText =
            data.message || 'Unable to register staff.';

    } catch (error) {

        console.error(error);

        message.className =
            'mb-4 p-3 rounded-lg bg-red-100 text-red-700';

        message.innerText =
            'Something went wrong. Please try again.';

    } finally {

        button.disabled = false;
        button.innerText = 'Register Staff';

    }

});
</script>

</body>

</html>
