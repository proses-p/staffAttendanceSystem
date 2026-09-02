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

        <div class="mb-6">

            <h1 class="text-2xl font-bold text-gray-800">
                Daily Attendance Report
            </h1>

            <p class="text-gray-500 mt-1">
                {{ $today->format('l, d F Y') }}
            </p>

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
                                        Present
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

</body>

</html>
