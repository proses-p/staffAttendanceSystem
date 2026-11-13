<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\User;
use Carbon\Carbon;

// use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->company_id;
        $today = Carbon::today();
        $staff = User::where('company_id', $companyId)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->with('shift')
            ->with([
                'attendances' => function ($query) use ($today, $companyId) {
                    $query->where('company_id', $companyId)
                        ->whereDate('attendance_date', $today);
                },
            ])
            ->get();
        $setting = AttendanceSetting::where('company_id', $companyId)->first();

        return view('admin.attendance.index', compact('staff', 'today', 'setting'));
    }
}
