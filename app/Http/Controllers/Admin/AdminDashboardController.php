<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return view('admin.dashboard', compact('staff', 'today'));
    }
}