<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use Illuminate\Http\Request;

class AttendanceSettingController extends Controller
{
    public function index() {
        $setting = AttendanceSetting::first();
        return view('admin.time-settings', compact('setting'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'expected_arrival_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        AttendanceSetting::updateOrCreate(
            ['id' => 1],
            [
                'expected_arrival_time' => $data['expected_arrival_time'],
            ]
        );
        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Expected arrival time updated successfully.');
    }
}
