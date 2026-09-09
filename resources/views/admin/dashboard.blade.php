
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard · Attendance</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

    <div class="min-h-screen flex flex-col lg:flex-row">

        @include('components.admin-sidebar')

        <main class="flex-1 p-4 sm:p-6 lg:p-10">

            <div class="mx-auto max-w-7xl">

                <header class="mb-8 flex flex-col gap-5 border-b border-slate-200 pb-7 sm:flex-row sm:items-end sm:justify-between">

                    <div>
                        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">
                            <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                Current distance:
                                @if ($officeLocation)
                                    {{ $officeLocation->allowed_radius }}m
                                @else
                                    Not configured
                                @endif
                        </div>

                        <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                            Welcome, admin.
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            {{ $today->format('l, d F Y') }}
                            <span class="mx-2 text-slate-300">/</span>
                            Your attendance overview at a glance.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">

                        {{-- Invite Staff --}}
                        <button
                            type="button"
                            onclick="openStaffModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                            </svg>

                            Invite staff
                        </button>

                        {{-- Save Location --}}
                        <button
                            type="button"
                            onclick="openLocationModal()"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                        >
                            <svg
                                class="h-4 w-4 text-slate-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.75"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M17.657 16.657L13.414 21a2 2 0 01-2.828 0l-4.243-4.343a8 8 0 1111.314 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>

                            Save location
                        </button>

                        
                    </div>
                </header>


                @if (session('success'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ session('error') }}
                    </div>
                @endif

                <div id="dashboardView">
                    <x-admin-statistics />
                </div>


                <section
                    id="usersView"
                    class="hidden"
                    aria-labelledby="usersHeading"
                >

                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

                        <div>
                            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">
                                <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                Directory
                            </div>

                            <h2
                                id="usersHeading"
                                class="text-3xl font-semibold tracking-tight text-slate-950"
                            >
                                Users
                            </h2>

                            <p class="mt-2 text-sm text-slate-500">
                                Manage your team directory from one place.
                            </p>
                        </div>

                        <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                            {{ $staff->count() }} users
                        </span>

                    </div>


                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

                        <div class="overflow-x-auto">

                            <table class="min-w-180 w-full text-left">

                                <thead class="border-b border-slate-200 bg-slate-50/80">

                                    <tr class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">

                                        <th class="px-6 py-4">Staff name</th>
                                        <th class="px-6 py-4">Date</th>
                                        <th class="px-6 py-4">Sign in</th>
                                        <th class="px-6 py-4">Sign out</th>
                                        <th class="px-6 py-4">Work duration</th>
                                        <th class="px-6 py-4">Attendance status</th>
                                        <th class="px-6 py-4 text-right">Actions</th>

                                    </tr>

                                </thead>


                                <tbody class="divide-y divide-slate-100">

                                    @forelse ($staff as $member)

                                        @php
                                            $todayAttendance = $member->attendances->first();
                                        @endphp

                                        <tr class="transition hover:bg-slate-50/70">

                                            <td class="px-6 py-4">

                                                <div class="flex items-center gap-3">

                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-sm font-semibold text-cyan-700">
                                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                                    </span>

                                                    <div>

                                                        <p class="text-sm font-semibold text-slate-900">
                                                            {{ $member->name }}
                                                        </p>

                                                        <p class="mt-0.5 text-xs text-slate-400">{{ $member->email }}</p>

                                                    </div>

                                                </div>

                                            </td>


                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                {{ $today->format('d M Y') }}
                                            </td>

                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                {{ $todayAttendance?->check_in_time?->format('h:i A') ?? '—' }}
                                            </td>

                                            <td class="px-6 py-4 text-sm text-slate-600">
                                                {{ $todayAttendance?->check_out_time?->format('h:i A') ?? '—' }}
                                            </td>

                                            <td class="px-6 py-4 text-sm font-medium text-slate-600">
                                                {{ $todayAttendance?->work_duration ?? '—' }}
                                            </td>


                                            <td class="px-6 py-4">

                                                @if ($todayAttendance?->check_out_time)
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-500"></span>
                                                        Signed Out
                                                    </span>
                                                @elseif ($todayAttendance)
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                        In Office
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                        Not Signed In
                                                    </span>
                                                @endif

                                            </td>


                                            <td class="px-6 py-4">

                                                <div class="flex justify-end gap-1">

                                                    <a
                                                        href="{{ route('staff.show', $member) }}"
                                                        aria-label="View {{ $member->name }}"
                                                        title="View"
                                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-cyan-50 hover:text-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500"
                                                    >
                                                        <svg
                                                            class="h-5 w-5"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1.75"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M2.46 12S4.91 5 12 5s9.54 7 9.54 7S19.09 19 12 19s-9.54-7-9.54-7Z"
                                                            />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </a>


                                                    <a
                                                        href="{{ route('staff.edit', $member) }}"
                                                        aria-label="Edit {{ $member->name }}"
                                                        title="Edit"
                                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-amber-50 hover:text-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                                    >
                                                        <svg
                                                            class="h-5 w-5"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1.75"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="m4 16-.5 4.5L8 20l11.5-11.5a2.12 2.12 0 0 1-3-3L5 17Z"
                                                            />
                                                            <path
                                                                stroke-linecap="round"
                                                                d="m14.5 7.5 2 2"
                                                            />
                                                        </svg>
                                                    </a>


                                                    <form
                                                        method="POST"
                                                        action="{{ route('staff.destroy', $member) }}"
                                                        onsubmit="return confirm('Are you sure you want to delete {{ addslashes($member->name) }}?');"
                                                        class="inline"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                        aria-label="Delete {{ $member->name }}"
                                                        title="Delete"
                                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500"
                                                        >
                                                        <svg
                                                            class="h-5 w-5"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1.75"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M4 7h16m-10 4v5m4-5v5M9 7V4h6v3m-9 0 1 13h10l1-13"
                                                            />
                                                        </svg>
                                                        </button>
                                                    </form>

                                                </div>

                                            </td>

                                        </tr>

                                    @empty

                                        <tr>
                                            <td
                                                colspan="6"
                                                class="px-6 py-12 text-center text-sm text-slate-500"
                                            >
                                                No users found.
                                            </td>
                                        </tr>

                                    @endforelse

                                </tbody>

                            </table>

                        </div>

                    </div>

                </section>

                <section
                    id="distanceView"
                    class="hidden"
                    aria-labelledby="distanceHeading"
                >
                    <div class="mb-6">
                        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">
                            <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                            Attendance boundary
                        </div>

                        <h2 id="distanceHeading" class="text-3xl font-semibold tracking-tight text-slate-950">
                            Distance
                        </h2>

                        <p class="mt-2 text-sm text-slate-500">
                            Set how close staff must be to the office when signing in or out.
                        </p>
                    </div>

                    <div class="max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <form method="POST" action="{{ route('admin.office-location.store', [], false) }}">
                            @csrf

                            <label for="allowed_radius" class="mb-2 block text-sm font-semibold text-slate-700">
                                Allowed distance (meters)
                            </label>
                            <input
                                type="number"
                                id="allowed_radius"
                                name="allowed_radius"
                                min="1"
                                step="1"
                                value="{{ old('allowed_radius', $officeLocation?->allowed_radius) }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500"
                                required
                            >
                            @error('allowed_radius')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror

                            <p class="mt-2 text-xs text-slate-500">
                                Current distance: {{ $officeLocation?->allowed_radius ?? 'Not configured' }}m
                            </p>

                            <button
                                type="submit"
                                class="mt-5 rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
                            >
                                Save distance
                            </button>
                        </form>
                    </div>
                </section>


            </div>

        </main>

    </div>


    {{-- Invite Staff Modal --}}

    <div
        id="staffModal"
        class="hidden fixed inset-0 bg-black/50 items-center justify-center z-50 p-4"
    >

        <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-6">

            <div class="flex justify-between items-center mb-5">

                <h2 class="text-xl font-bold">
                    Invite Staff
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


            <form
                id="staffForm"
                method="POST"
            >

                @csrf

                <div class="space-y-4">

                    <input
                        type="email"
                        name="email"
                        placeholder="Staff Email"
                        class="w-full border rounded-lg px-3 py-2"
                        required
                    >

                </div>


                <button
                    type="submit"
                    id="registerStaffBtn"
                    class="w-full bg-blue-600 text-white py-2.5 rounded-lg mt-5"
                >
                    Send Invitation
                </button>

            </form>

        </div>

    </div>


    <!-- Office Location Modal -->

    <div
        id="locationModal"
        class="hidden fixed inset-0 z-50 bg-black/50 items-center justify-center px-4"
    >

        <div class="bg-white w-full max-w-sm rounded-xl shadow-xl p-6">

            <h2 class="text-lg font-semibold text-gray-900">
                Save Office Location
            </h2>

            <p class="text-sm text-gray-500 mt-2">
                Make sure you are around the office before saving
                the office location.
            </p>

            <div id="locationMessage" class="hidden mt-4 rounded-lg px-3 py-2 text-sm"></div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <label for="officeLatitude" class="mb-1 block text-xs font-semibold text-gray-600">Latitude</label>
                    <input type="number" id="officeLatitude" step="any" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" readonly>
                </div>
                <div>
                    <label for="officeLongitude" class="mb-1 block text-xs font-semibold text-gray-600">Longitude</label>
                    <input type="number" id="officeLongitude" step="any" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" readonly>
                </div>
            </div>

            <div class="mt-4">
                <label for="officeAddress" class="mb-1 block text-xs font-semibold text-gray-600">Detected location</label>
                <input type="text" id="officeAddress" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Resolving location..." readonly>
            </div>

            <div id="officeLocationMap" class="mt-4 h-56 w-full rounded-lg"></div>


            <div class="flex justify-end gap-3 mt-6">

                <button
                    type="button"
                    onclick="closeLocationModal()"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700"
                >
                    Cancel
                </button>


                <button
                    type="button"
                    onclick="saveOfficeLocation()"
                    id="saveLocationButton"
                    class="px-4 py-2 rounded-lg bg-blue-700 text-white"
                >
                    Save
                </button>

            </div>

        </div>

    </div>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>

    function showUsersView() {

        document.getElementById('dashboardView').classList.add('hidden');
        document.getElementById('distanceView').classList.add('hidden');

        document.getElementById('usersView').classList.remove('hidden');

    }


    function showDashboardView() {

        document.getElementById('usersView').classList.add('hidden');
        document.getElementById('distanceView').classList.add('hidden');

        document.getElementById('dashboardView').classList.remove('hidden');

    }


    function showDistanceView() {

        document.getElementById('dashboardView').classList.add('hidden');
        document.getElementById('usersView').classList.add('hidden');

        document.getElementById('distanceView').classList.remove('hidden');

    }

    if (window.location.hash === '#users') {
        showUsersView();
    }


    function openLocationModal() {

        const modal = document.getElementById('locationModal');

        modal.classList.remove('hidden');

        modal.classList.add('flex');

        initializeLocationMap();
        getOfficeLocation();

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


    let officeLocationMap;
    let officeLocationMarker;
    let officeLocationLookupId = 0;


    function initializeLocationMap() {

        if (officeLocationMap) {
            officeLocationMap.invalidateSize();
            return;
        }

        officeLocationMap = L.map('officeLocationMap').setView([0, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(officeLocationMap);

    }


    function setOfficeLocation(latitude, longitude) {

        const numericLatitude = Number(latitude);
        const numericLongitude = Number(longitude);
        const addressElement = document.getElementById('officeAddress');
        const saveButton = document.getElementById('saveLocationButton');
        const lookupId = ++officeLocationLookupId;

        document.getElementById('officeLatitude').value = numericLatitude.toFixed(7);
        document.getElementById('officeLongitude').value = numericLongitude.toFixed(7);
        addressElement.value = 'Resolving location...';
        saveButton.disabled = true;

        const coordinates = [numericLatitude, numericLongitude];

        if (!officeLocationMarker) {
            officeLocationMarker = L.marker(coordinates, { draggable: true }).addTo(officeLocationMap);
            officeLocationMarker.on('dragend', function (event) {
                const position = event.target.getLatLng();
                setOfficeLocation(position.lat, position.lng);
            });
        } else {
            officeLocationMarker.setLatLng(coordinates);
        }

        officeLocationMap.setView(coordinates, 17);

        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(numericLatitude)}&lon=${encodeURIComponent(numericLongitude)}&zoom=18&addressdetails=1`, {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Reverse geocoding failed.');
            }

            return response.json();
        })
        .then(data => {
            if (lookupId !== officeLocationLookupId) {
                return;
            }

            addressElement.value = data.display_name || 'Address not found for these coordinates.';
            saveButton.disabled = !data.display_name;
        })
        .catch(error => {
            if (lookupId !== officeLocationLookupId) {
                return;
            }

            console.error(error);
            addressElement.value = 'Unable to determine the address for these coordinates.';
            showLocationMessage('Unable to determine the location address. Please try again.', true);
            saveButton.disabled = true;
        });

    }


    function showLocationMessage(message, isError = false) {

        const messageElement = document.getElementById('locationMessage');

        messageElement.innerText = message;
        messageElement.className = isError
            ? 'mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700'
            : 'mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700';

    }


    function getOfficeLocation() {

        const button = document.getElementById('saveLocationButton');

        button.disabled = true;

        button.innerText = 'Locating...';


        if (!navigator.geolocation) {

            showLocationMessage('Location services are not supported by this browser.', true);

            button.disabled = false;

            button.innerText = 'Save';

            return;

        }


        navigator.geolocation.getCurrentPosition(

            function(position) {

                const latitude = position.coords.latitude;

                const longitude = position.coords.longitude;


                setOfficeLocation(latitude, longitude);
                showLocationMessage('Location detected. Drag the marker to adjust it before saving.');
                button.innerText = 'Save';

            },


            function(error) {

                const message = error.code === error.PERMISSION_DENIED
                    ? 'Location permission was denied. Please allow access and try again.'
                    : 'Unable to detect your location. Please try again.';

                showLocationMessage(message, true);

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


    function saveOfficeLocation() {

        const button = document.getElementById('saveLocationButton');
        const latitude = document.getElementById('officeLatitude').value;
        const longitude = document.getElementById('officeLongitude').value;

        if (!latitude || !longitude) {
            showLocationMessage('Detect your location before saving.', true);
            return;
        }

        button.disabled = true;
        button.innerText = 'Saving...';

        fetch("{{ route('admin.office-location.store', [], false) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                latitude: Number(latitude),
                longitude: Number(longitude),
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeLocationModal();
            } else {
                showLocationMessage('Unable to save office location.', true);
            }

            button.disabled = false;
            button.innerText = 'Save';
        })
        .catch(error => {
            console.error(error);
            showLocationMessage('Something went wrong while saving the location.', true);
            button.disabled = false;
            button.innerText = 'Save';
        });

    }


    // Invite Staff

    document.getElementById('staffForm').addEventListener('submit', async function(event) {

        event.preventDefault();


        const form = this;

        const button = document.getElementById('registerStaffBtn');

        const message = document.getElementById('staffFormMessage');


        button.disabled = true;

        button.innerText = 'Sending...';


        const formData = new FormData(form);


        try {

            const response = await fetch(
                "{{ route('admin.invitations.store') }}",
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


            if (response.ok) {

                message.className =
                    'mb-4 p-3 rounded-lg bg-green-100 text-green-700';

                message.innerText =
                    data.message || 'Invitation created successfully.';


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
                    errors || 'Please check the email provided.';


                return;

            }


            message.className =
                'mb-4 p-3 rounded-lg bg-red-100 text-red-700';


            message.innerText =
                data.message || 'Unable to send invitation.';


        } catch (error) {

            console.error(error);


            message.className =
                'mb-4 p-3 rounded-lg bg-red-100 text-red-700';


            message.innerText =
                'Something went wrong. Please try again.';

        } finally {

            button.disabled = false;

            button.innerText = 'Send Invitation';

        }

    });

</script>


</body>

</html>

