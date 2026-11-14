<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use App\Services\CompanyHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $leaveRequests = StaffLeaveRecord::query()
            ->where('company_id', $request->user()->company_id)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->with('user')
            ->get()
            ->map(fn (StaffLeaveRecord $leave): array => $this->leaveData($leave));

        return response()->json(['leave_requests' => $leaveRequests]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type' => ['required', 'string', Rule::in(StaffLeaveRecord::TYPES)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $leave = DB::transaction(function () use ($data, $user): StaffLeaveRecord {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $overlaps = StaffLeaveRecord::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])
                ->exists();

            if ($overlaps) {
                abort(422, 'This leave overlaps an existing pending or approved record.');
            }

            $leave = new StaffLeaveRecord([
                ...$data,
                'user_id' => $user->id,
                'status' => 'pending',
                'request_source' => 'staff_request',
            ]);
            $leave->company_id = $user->company_id;
            $leave->save();

            return $leave;
        });

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'leave_request' => $this->leaveData($leave->load('user')),
        ], 201);
    }

    private function leaveData(StaffLeaveRecord $leave): array
    {
        return [
            'id' => $leave->id,
            'leave_type' => $leave->leave_type,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'leave_days' => $leave->user
                ? app(CompanyHolidayService::class)->scheduledLeaveDays(
                    $leave->user,
                    $leave->start_date,
                    $leave->end_date
                )
                : null,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'decision_note' => $leave->decision_note,
            'submitted_at' => $leave->created_at?->toISOString(),
        ];
    }
}
