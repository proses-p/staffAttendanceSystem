<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\FieldExpense;
use App\Models\OfficeLocation;
use App\Models\StaffInvitation;
use App\Models\User;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->company_id;
        $today = Carbon::today();

        StaffInvitation::query()
            ->where('company_id', $companyId)
            ->whereNull('used_at')
            ->where('expires_at', '<', now())
            ->delete();

        $invitations = StaffInvitation::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->paginate(5);

        $staff = User::where('company_id', $companyId)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->with([
                'attendances' => function ($query) use ($today, $companyId) {
                    $query->where('company_id', $companyId)
                        ->whereDate('attendance_date', $today);
                },
            ])
            ->get();

        $officeLocation = OfficeLocation::where('company_id', $companyId)->first();

        $attendanceTrend = Attendance::query()
            ->where('company_id', $companyId)
            ->whereHas('user', fn ($query) => $query->where('company_id', $companyId))
            ->whereBetween('attendance_date', [$today->copy()->subDays(13), $today])
            ->select('attendance_date')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(fn ($attendance) => $attendance->attendance_date->format('Y-m-d'));

        $pendingFieldExpenses = FieldExpense::query()
            ->where('status', FieldExpense::STATUS_PENDING)
            ->whereHas('fieldAssignment', fn ($query) => $query->where('company_id', $companyId))
            ->count();

        return view('admin.dashboard', compact(
            'staff',
            'today',
            'officeLocation',
            'attendanceTrend',
            'pendingFieldExpenses',
            'invitations'
        ));
    }
}
