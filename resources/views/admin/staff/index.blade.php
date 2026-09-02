<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Staff Management</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-6">

    <div class="max-w-6xl mx-auto">

        <div class="flex items-center justify-between mb-6">

            <div>
                <h1 class="text-2xl font-bold">
                    Staff Management
                </h1>

                <p class="text-gray-500">
                    Manage Dudumizi staff accounts
                </p>
            </div>

            <a
                href="{{ route('admin.staff.create') }}"
                class="bg-blue-600 text-white px-5 py-2 rounded-lg"
            >
                + Add Staff
            </a>

        </div>


        <div class="bg-white rounded-xl shadow overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-50">

                    <tr>
                        <th class="text-left p-4">Name</th>
                        <th class="text-left p-4">Email</th>
                        <th class="text-left p-4">Phone</th>
                        <th class="text-left p-4">Employee ID</th>
                        <th class="text-left p-4">Status</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse ($staff as $member)

                        <tr class="border-t">

                            <td class="p-4">
                                {{ $member->name }}
                            </td>

                            <td class="p-4">
                                {{ $member->email }}
                            </td>

                            <td class="p-4">
                                {{ $member->phone ?? 'N/A' }}
                            </td>

                            <td class="p-4">
                                {{ $member->employee_id ?? 'N/A' }}
                            </td>

                            <td class="p-4">

                                @if ($member->is_active)

                                    <span class="text-green-600">
                                        Active
                                    </span>

                                @else

                                    <span class="text-red-600">
                                        Inactive
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="p-8 text-center text-gray-500"
                            >
                                No staff members found.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="mt-6">

            {{ $staff->links() }}

        </div>

    </div>

</body>

</html>
