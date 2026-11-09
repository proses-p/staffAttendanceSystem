<?php

namespace App\Services;

use App\Models\FieldAssignment;
use Carbon\Carbon;

class VelocityService
{
    public function __construct(private readonly GeoService $geo)
    {
    }

    public function check(int $userId, float $lat, float $lng, ?Carbon $at = null): ?string
    {
        $at ??= now();
        $windowStart = $at->copy()->subMinutes((int) config('attendance.velocity_window_min'));
        $previous = FieldAssignment::query()
            ->where('user_id', $userId)
            ->whereRaw('COALESCE(last_location_at, clock_in_at) BETWEEN ? AND ?', [
                $windowStart->toDateTimeString(),
                $at->toDateTimeString(),
            ])
            ->orderByRaw('COALESCE(last_location_at, clock_in_at) DESC')
            ->first();

        if (! $previous) {
            return null;
        }

        $previousLat = $previous->last_lat ?? $previous->clock_in_lat;
        $previousLng = $previous->last_lng ?? $previous->clock_in_lng;
        if ($previousLat === null || $previousLng === null) {
            return null;
        }

        $distance = $this->geo->distanceKm((float) $previousLat, (float) $previousLng, $lat, $lng);
        if ($distance < (float) config('attendance.min_distance_km')) {
            return null;
        }

        $seconds = max(abs($at->diffInSeconds(
            Carbon::parse($previous->last_location_at ?? $previous->clock_in_at)
        )), 1);
        $minutes = $seconds / 60;
        $speed = $distance / ($seconds / 3600);

        // Optional candidate: the user's latest office attendance coordinates (placeholder columns).
        // Add that point to the candidate set only if office-location sampling is explicitly approved.

        return $speed > (float) config('attendance.max_speed_kmh')
            ? sprintf('velocity_anomaly: %.1f km in %.1f min (%.0f km/h)', $distance, $minutes, $speed)
            : null;
    }
}
