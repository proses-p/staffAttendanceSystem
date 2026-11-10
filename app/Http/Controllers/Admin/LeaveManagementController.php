<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveManagementController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()?->company_id;
        abort_unless($companyId, 403);

        StaffLeaveRecord::query()
            ->where('company_id', $companyId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $filters = $request->validate([
            'leave_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        return view('admin.leaves', [
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()?->company_id;
        abort_unless($companyId, 403);

        $data = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('role', 'staff')
                    ->where('company_id', $companyId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['status'] === 'approved' && $this->hasAttendance($data, $companyId)) {
            return back()
                ->withInput()
                ->withErrors(['start_date' => 'Leave cannot be approved for dates that already have attendance records.']);
        }

        $overlaps = StaffLeaveRecord::query()
            ->where('company_id', $companyId)
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
        $leaveRecord->company_id = $companyId;
        $leaveRecord->save();

        return redirect()
            ->route('admin.leaves.index')
            ->with('success', 'Leave record added.');
    }

    public function update(Request $request, StaffLeaveRecord $leaveRecord): RedirectResponse
    {
        $companyId = $request->user()?->company_id;
        abort_unless($companyId && (int) $leaveRecord->company_id === (int) $companyId, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $leaveRecord, $companyId): void {
            $lockedLeave = StaffLeaveRecord::query()
                ->where('company_id', $companyId)
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
                ], $companyId)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Leave cannot be approved for dates that already have attendance records.',
                ]);
            }

            $lockedLeave->update($data);
        });

        return redirect()
            ->route('admin.leaves.index')
            ->with('success', 'Leave status updated.');
    }

    private function hasAttendance(array $leaveData, int|string $companyId): bool
    {
        return Attendance::query()
            ->where('company_id', $companyId)
            ->where('user_id', $leaveData['user_id'])
            ->whereDate('attendance_date', '>=', $leaveData['start_date'])
            ->whereDate('attendance_date', '<=', $leaveData['end_date'])
            ->exists();
    }
}
