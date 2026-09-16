<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\User;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $staff = User::where('role', 'staff')
            ->where('is_active', true)
            ->with([
                'attendances' => function ($query) use ($today) {
                    $query->whereDate('attendance_date', $today);
                }
            ])
            ->get();

        $officeLocation = OfficeLocation::first();

        $attendanceTrend = Attendance::query()
            ->whereBetween('attendance_date', [$today->copy()->subDays(13), $today])
            ->select('attendance_date')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(fn ($attendance) => $attendance->attendance_date->format('Y-m-d'));

        return view('admin.dashboard', compact('staff', 'today', 'officeLocation', 'attendanceTrend'));
    }
}