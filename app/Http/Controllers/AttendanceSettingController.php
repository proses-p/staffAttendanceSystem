<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceSettingController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'leave_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        return view('admin.time-settings', [
            'setting' => AttendanceSetting::where('company_id', $companyId)->first(),
            'holidays' => AttendanceHoliday::query()
                ->where('company_id', $companyId)
                ->orderBy('holiday_date')
                ->get(),
            'leaveRecords' => StaffLeaveRecord::query()
                ->where('company_id', $companyId)
                ->whereHas('user', fn ($query) => $query->where('company_id', $companyId))
                ->with('user:id,name,email')
                ->when(
                    $filters['leave_status'] ?? null,
                    fn ($query, $status) => $query->where('status', $status)
                )
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'leaveStatus' => $filters['leave_status'] ?? null,
            'leaveCounts' => StaffLeaveRecord::query()
                ->where('company_id', $companyId)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'staff' => User::query()
                ->where('company_id', $companyId)
                ->where('role', 'staff')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
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

    public function storeLeave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('role', 'staff')
                    ->where('company_id', $request->user()->company_id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['status'] === 'approved' && $this->hasAttendance($data)) {
            return back()
                ->withInput()
                ->withErrors(['start_date' => 'Leave cannot be approved for dates that already have attendance records.']);
        }

        $overlaps = StaffLeaveRecord::query()
            ->where('company_id', $request->user()->company_id)
            ->where('user_id', $data['user_id'])
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlaps) {
            return back()
                ->withInput()
                ->withErrors(['start_date' => 'This leave overlaps an existing record for the staff member.']);
        }

        $data['recorded_by'] = $request->user()->id;
        $data['leave_type'] = StaffLeaveRecord::TYPE_GENERIC;
        $data['request_source'] = 'admin_record';
        $leaveRecord = new StaffLeaveRecord($data);
        $leaveRecord->company_id = $request->user()->company_id;
        $leaveRecord->save();

        return redirect()
            ->route('admin.time-settings.index')
            ->with('success', 'Leave record added.');
    }

    public function updateLeaveStatus(
        Request $request,
        StaffLeaveRecord $leaveRecord
    ): RedirectResponse {
        abort_unless(
            (int) $leaveRecord->company_id === (int) $request->user()->company_id,
            404
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $leaveRecord): void {
            $lockedLeave = StaffLeaveRecord::query()
                ->whereKey($leaveRecord->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLeave->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending leave requests can be decided.',
                ]);
            }

            if (
                $data['status'] === 'approved' &&
                $this->hasAttendance([
                    'user_id' => $lockedLeave->user_id,
                    'start_date' => $lockedLeave->start_date->toDateString(),
                    'end_date' => $lockedLeave->end_date->toDateString(),
                ])
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Leave cannot be approved for dates that already have attendance records.',
                ]);
            }

            $lockedLeave->update($data);
        });

        return redirect()
            ->route('admin.time-settings.index')
            ->with('success', 'Leave status updated.');
    }

    private function hasAttendance(array $leaveData): bool
    {
        return Attendance::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('user_id', $leaveData['user_id'])
            ->whereDate('attendance_date', '>=', $leaveData['start_date'])
            ->whereDate('attendance_date', '<=', $leaveData['end_date'])
            ->exists();
    }
}
