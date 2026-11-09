<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\FieldAssignment;
use App\Models\StaffLeaveRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminFieldOperationsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $pendingLeave = StaffLeaveRecord::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();
        $pendingMissedPunches = $this->pendingMissedPunches($companyId);

        return response()->json([
            'currently_on_field' => $this->onFieldQuery($companyId)->count(),
            'missing_personnel' => count($this->missingPersonnel($request)),
            'pending_approvals' => [
                'leave_requests' => $pendingLeave,
                'missed_punch_requests' => $pendingMissedPunches,
                'total' => $pendingLeave + $pendingMissedPunches,
            ],
            'flagged_assignments' => FieldAssignment::query()
                ->where('company_id', $companyId)
                ->where('is_flagged', true)
                ->whereNull('reviewed_at')
                ->count(),
        ]);
    }

    public function onField(Request $request): JsonResponse
    {
        $rows = $this->onFieldQuery($request->user()->company_id)
            ->with('user:id,name')
            ->orderBy('clock_in_at')
            ->get()
            ->map(fn (FieldAssignment $assignment): array => [
                'assignment_id' => $assignment->id,
                'name' => $assignment->user->name,
                'task_type' => $assignment->task_type,
                'purpose' => $assignment->purpose_description,
                'clock_in_at' => $assignment->clock_in_at?->toIso8601String(),
                'latitude' => $assignment->last_lat ?? $assignment->clock_in_lat,
                'longitude' => $assignment->last_lng ?? $assignment->clock_in_lng,
                'last_seen_at' => ($assignment->last_location_at ?? $assignment->clock_in_at)?->toIso8601String(),
                'is_flagged' => $assignment->is_flagged,
            ]);

        return response()->json(['on_field' => $rows]);
    }

    public function flagged(Request $request): JsonResponse
    {
        $assignments = FieldAssignment::query()
            ->where('company_id', $request->user()->company_id)
            ->where('is_flagged', true)
            ->whereNull('reviewed_at')
            ->with('user:id,name,email')
            ->orderByDesc('clock_in_at')
            ->paginate(25);

        return response()->json([
            'data' => $assignments->getCollection()->map(fn (FieldAssignment $assignment): array => [
                'assignment_id' => $assignment->id,
                'name' => $assignment->user->name,
                'task_type' => $assignment->task_type,
                'purpose' => $assignment->purpose_description,
                'clock_in_at' => $assignment->clock_in_at?->toIso8601String(),
                'clock_out_at' => $assignment->clock_out_at?->toIso8601String(),
                'risk_score' => $assignment->risk_score,
                'flag_reasons' => $assignment->flag_reasons ?? [],
                'is_mock_location' => $assignment->is_mock_location,
            ]),
            'current_page' => $assignments->currentPage(),
            'last_page' => $assignments->lastPage(),
            'total' => $assignments->total(),
        ]);
    }

    public function review(Request $request, FieldAssignment $fieldAssignment): JsonResponse
    {
        abort_unless((int) $fieldAssignment->company_id === (int) $request->user()->company_id, 404);
        if (! $fieldAssignment->is_flagged || $fieldAssignment->reviewed_at) {
            throw ValidationException::withMessages([
                'assignment' => ['Only unreviewed flagged assignments can be reviewed.'],
            ]);
        }

        $fieldAssignment->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Field assignment reviewed.']);
    }

    public function missing(Request $request): JsonResponse
    {
        return response()->json(['missing_personnel' => $this->missingPersonnel($request)]);
    }

    private function onFieldQuery(int $companyId)
    {
        return FieldAssignment::query()->where('company_id', $companyId)->open();
    }

    private function missingPersonnel(Request $request): array
    {
        $user = $request->user();
        $today = today();
        $setting = AttendanceSetting::query()->where('company_id', $user->company_id)->first();
        if (! $setting?->expected_arrival_time) {
            return [];
        }
        if (! is_array($setting->working_days) || $setting->working_days === []) {
            return [];
        }
        if (! in_array((int) $today->dayOfWeekIso, array_map('intval', $setting->working_days), true)) {
            return [];
        }
        if (now()->format('H:i') < $setting->expected_arrival_time->format('H:i')) {
            return [];
        }

        return User::query()
            ->where('company_id', $user->company_id)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->whereDoesntHave('attendances', fn ($query) => $query
                ->where('company_id', $user->company_id)
                ->whereDate('attendance_date', $today))
            ->whereDoesntHave('fieldAssignments', fn ($query) => $query
                ->where('company_id', $user->company_id)
                ->where(fn ($active) => $active
                    ->whereDate('clock_in_at', $today)
                    ->orWhereNull('clock_out_at')))
            ->whereDoesntHave('leaveRecords', fn ($query) => $query
                ->where('company_id', $user->company_id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $staff): array => [
                'user_id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
            ])
            ->all();
    }

    private function pendingMissedPunches(int $companyId): int
    {
        $table = 'missed_punch_requests';
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->where('status', 'pending');
        if (Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn($table, 'user_id')) {
            $query->whereIn('user_id', User::query()
                ->where('company_id', $companyId)
                ->select('id'));
        } else {
            return 0;
        }

        return $query->count();
    }
}
