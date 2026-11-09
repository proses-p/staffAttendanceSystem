<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClientVisit;
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
        $fieldAssignments = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('clock_in_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('clock_out_at')->orWhere('clock_out_at', '>=', $monthStart))
            ->get();

        $fieldIntervals = $fieldAssignments->map(fn (FieldAssignment $assignment): array => [
            $assignment->clock_in_at->copy()->max($monthStart),
            ($assignment->clock_out_at ?? $now)->copy()->min($now),
        ])->all();

        $attendanceIntervals = Attendance::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $now->toDateString()])
            ->get()
            ->map(function (Attendance $attendance) use ($monthStart, $now): ?array {
                $start = $attendance->check_in_at;
                if (! $start) {
                    return null;
                }
                $end = $attendance->check_out_at ?? $now;

                return [$start->copy()->max($monthStart), $end->copy()->min($now)];
            })
            ->filter()
            ->all();

        $combinedMinutes = $this->mergedMinutesByDay([...$fieldIntervals, ...$attendanceIntervals]);
        $fieldMinutes = $this->mergedMinutesByDay($fieldIntervals);
        $daysWithRecords = count($combinedMinutes);
        $totalMinutes = array_sum($combinedMinutes);
        $visits = ClientVisit::query()
            ->where('user_id', $user->id)
            ->whereHas('assignment', fn ($query) => $query->where('company_id', $user->company_id))
            ->whereNotNull('departed_at')
            ->whereBetween('arrived_at', [$monthStart, $now])
            ->count();

        return response()->json([
            'month' => $now->format('F Y'),
            'total_field_hours' => round(array_sum($fieldMinutes) / 60, 1),
            'avg_daily_hours' => $daysWithRecords === 0
                ? 0
                : round(($totalMinutes / 60) / $daysWithRecords, 1),
            'total_field_visits' => $visits,
        ]);
    }

    private function mergedMinutesByDay(array $intervals): array
    {
        $byDay = [];
        foreach ($intervals as [$start, $end]) {
            if ($end->lt($start)) {
                continue;
            }
            for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                $dayStart = $day->copy()->max($start);
                $dayEnd = $day->copy()->endOfDay()->min($end);
                $byDay[$day->toDateString()][] = [$dayStart, $dayEnd];
            }
        }

        $minutes = [];
        foreach ($byDay as $date => $dayIntervals) {
            usort($dayIntervals, fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());
            $merged = [];
            foreach ($dayIntervals as [$start, $end]) {
                $last = array_key_last($merged);
                if ($last === null || $start->gt($merged[$last][1])) {
                    $merged[] = [$start, $end];
                } elseif ($end->gt($merged[$last][1])) {
                    $merged[$last][1] = $end;
                }
            }
            $minutes[$date] = array_sum(array_map(
                fn (array $interval): int => max(0, (int) $interval[0]->diffInMinutes($interval[1])),
                $merged
            ));
        }

        return $minutes;
    }
}
