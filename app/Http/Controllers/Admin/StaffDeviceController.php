<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffDeviceController extends Controller
{
    public function index(Request $request): View
    {
        $devices = StaffDevice::query()
            ->with(['user:id,name,email,company_id', 'approver:id,name', 'revoker:id,name'])
            ->whereHas('user', fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->latest()
            ->paginate(25);

        return view('admin.devices.index', compact('devices'));
    }

    public function approve(Request $request, StaffDevice $device): RedirectResponse
    {
        DB::transaction(function () use ($request, $device): void {
            $staff = User::query()->whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $lockedDevice = StaffDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            $this->ensureCompanyScope($request, $staff);
            if ($lockedDevice->status !== 'pending') {
                throw ValidationException::withMessages([
                    'device' => 'Only pending devices can be approved.',
                ]);
            }

            $hasActiveDevice = StaffDevice::query()
                ->where('user_id', $staff->id)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->exists();
            if ($hasActiveDevice) {
                throw ValidationException::withMessages([
                    'device' => 'Revoke the current active device before approving its replacement.',
                ]);
            }

            $lockedDevice->update([
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Device approved.');
    }

    public function revoke(Request $request, StaffDevice $device): RedirectResponse
    {
        DB::transaction(function () use ($request, $device): void {
            $staff = User::query()->whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $lockedDevice = StaffDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $this->ensureCompanyScope($request, $staff);

            if ($lockedDevice->status === 'revoked') {
                throw ValidationException::withMessages(['device' => 'This device is already revoked.']);
            }

            $lockedDevice->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Device revoked. Its attendance authorization is now disabled.');
    }

    private function ensureCompanyScope(Request $request, User $staff): void
    {
        abort_unless(
            $staff->role === 'staff'
                && (int) $staff->company_id === (int) $request->user()->company_id,
            404
        );
    }
}
