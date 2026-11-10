<?php

namespace App\Http\Controllers;

use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceSettingController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('admin.time-settings', [
            'setting' => AttendanceSetting::where('company_id', $companyId)->first(),
            'holidays' => AttendanceHoliday::query()
                ->where('company_id', $companyId)
                ->orderBy('holiday_date')
                ->get(),
            'weekdays' => [
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expected_arrival_time' => ['required', 'date_format:H:i'],
            'expected_work_hours' => ['required', 'numeric', 'gt:0', 'lte:24'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'integer', 'distinct', 'between:1,7'],
        ]);

        $setting = AttendanceSetting::where('company_id', $request->user()->company_id)->first()
            ?? new AttendanceSetting;
        $setting->fill([
            'expected_arrival_time' => $data['expected_arrival_time'],
            'expected_work_minutes' => (int) round($data['expected_work_hours'] * 60),
            'grace_period_minutes' => $data['grace_period_minutes'],
            'working_days' => array_values(array_map(
                'intval',
                $data['working_days']
            )),
        ]);
        $setting->company_id = $request->user()->company_id;
        $setting->save();

        return redirect()
            ->route('admin.time-settings.index')
            ->with('success', 'Attendance policy saved successfully.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'holiday_date' => [
                'required',
                'date',
                Rule::unique('attendance_holidays', 'holiday_date')
                    ->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $holiday = new AttendanceHoliday($data);
        $holiday->company_id = $companyId;
        $holiday->save();

        return redirect()
            ->route('admin.time-settings.index')
            ->with('success', 'Holiday added successfully.');
    }

    public function destroyHoliday(Request $request, AttendanceHoliday $holiday): RedirectResponse
    {
        abort_unless(
            (int) $holiday->company_id === (int) $request->user()->company_id,
            404
        );
        $holiday->delete();

        return redirect()
            ->route('admin.time-settings.index')
            ->with('success', 'Holiday removed.');
    }

}
