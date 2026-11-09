<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff devices · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')
        <main class="flex-1 p-5 pt-8 sm:p-8">
            <div class="mx-auto max-w-7xl">
                <header class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">Security</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Staff devices</h1>
                    <p class="mt-2 text-sm text-slate-500">Review enrollment requests and manage device access for your company.</p>
                </header>

                @if (session('status'))
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">Staff member</th>
                                    <th class="px-5 py-4">Device</th>
                                    <th class="px-5 py-4">Registered</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4">Approval audit</th>
                                    <th class="px-5 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($devices as $device)
                                    <tr>
                                        <td class="px-5 py-4">
                                            <div class="font-semibold">{{ $device->user->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $device->user->email }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div>{{ $device->device_name ?: 'Unnamed Android device' }}</div>
                                            <div class="text-xs text-slate-500">{{ strtoupper($device->platform) }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">{{ $device->created_at->format('d M Y, H:i') }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold
                                                {{ $device->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($device->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                                {{ ucfirst($device->status) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-xs text-slate-600">
                                            @if ($device->approved_at)
                                                <div>Approved by {{ $device->approver?->name ?? 'former admin' }} · {{ $device->approved_at->format('d M Y H:i') }}</div>
                                            @endif
                                            @if ($device->revoked_at)
                                                Revoked by {{ $device->revoker?->name ?? 'former admin' }} · {{ $device->revoked_at->format('d M Y H:i') }}
                                            @elseif (!$device->approved_at)
                                                Awaiting review
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            @if ($device->status === 'pending')
                                                <form method="POST" action="{{ route('admin.devices.approve', $device) }}" onsubmit="return confirm('Approve this staff device?')">
                                                    @csrf
                                                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                                </form>
                                            @elseif ($device->status === 'active')
                                                <form method="POST" action="{{ route('admin.devices.revoke', $device) }}" onsubmit="return confirm('Revoke this device now? Attendance access will stop immediately.')">
                                                    @csrf
                                                    <button class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">Revoke</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-slate-400">No action</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No staff devices have been registered.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $devices->links() }}</div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
