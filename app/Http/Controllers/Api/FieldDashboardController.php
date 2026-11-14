<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldDashboardController extends Controller
{
    public function employee(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $fieldAssignments = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereNotNull('clock_out_at')
            ->whereBetween('clock_in_at', [$monthStart, $monthEnd])
            ->get();
        $minutesByDay = [];
        foreach ($fieldAssignments as $assignment) {
            if (! $assignment->clock_in_at || ! $assignment->clock_out_at ||
                $assignment->clock_out_at->lte($assignment->clock_in_at)) {
                continue;
            }
            $date = $assignment->clock_in_at->toDateString();
            $minutes = min(
                14 * 60,
                (int) floor($assignment->clock_in_at->diffInSeconds($assignment->clock_out_at) / 60)
            );
            $minutesByDay[$date] = min(14 * 60, ($minutesByDay[$date] ?? 0) + $minutes);
        }
        $totalMinutes = array_sum($minutesByDay);

        return response()->json([
            'month' => $now->format('F Y'),
            'total_field_hours' => round($totalMinutes / 60, 2),
            'avg_daily_hours' => $minutesByDay === []
                ? 0.0
                : round($totalMinutes / 60 / count($minutesByDay), 2),
            'total_field_visits' => $fieldAssignments->count(),
        ]);
    }
}
