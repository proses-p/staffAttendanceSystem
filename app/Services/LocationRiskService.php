<?php

namespace App\Services;

use Illuminate\Http\Request;

class LocationRiskService
{
    public function __construct(private readonly GeoService $geo)
    {
    }

    public function assess(array $data, Request $request): array
    {
        $score = 0;
        $reasons = [];
        $isMock = (bool) ($data['is_mocked'] ?? false);

        if ($isMock) {
            $score += 100;
            $reasons[] = 'mock_location_enabled';
        }
        if (($data['is_rooted'] ?? false) === true) {
            $score += 40;
            $reasons[] = 'rooted_or_jailbroken';
        }
        if (($data['is_emulator'] ?? false) === true) {
            $score += 60;
            $reasons[] = 'emulator_detected';
        }

        if (array_key_exists('accuracy', $data)) {
            $accuracy = (float) $data['accuracy'];
            if ($accuracy <= 0) {
                $score += 30;
                $reasons[] = 'zero_accuracy';
            } elseif ($accuracy > (float) config('attendance.max_accuracy_m')) {
                $score += 20;
                $reasons[] = 'poor_accuracy';
            }
        }

        if (abs((float) ($data['latitude'] ?? 0)) <= 0.001
            && abs((float) ($data['longitude'] ?? 0)) <= 0.001) {
            $score += 100;
            $reasons[] = 'null_island';
        }

        if (! ($data['skip_stale_check'] ?? false) && isset($data['captured_at'])) {
            $capturedAt = strtotime((string) $data['captured_at']);
            if ($capturedAt === false
                || abs(now()->getTimestamp() - $capturedAt) > (int) config('attendance.max_age_seconds')) {
                $score += 30;
                $reasons[] = 'stale_reading';
            }
        }

        if (! ($data['skip_ip_check'] ?? false)) {
            $ipLocation = $this->ipLocation($request->ip());
            if ($ipLocation !== null) {
                if ($this->geo->distanceKm(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    (float) $ipLocation['lat'],
                    (float) $ipLocation['lng']
                ) > (float) config('attendance.max_ip_gps_km')) {
                    $score += 25;
                    $reasons[] = 'ip_gps_mismatch';
                }
                if ($ipLocation['is_proxy'] ?? false) {
                    $score += 30;
                    $reasons[] = 'vpn_or_proxy_ip';
                }
            }
        }

        return [min(100, $score), array_values(array_unique($reasons)), $isMock];
    }

    protected function ipLocation(string $ip): ?array
    {
        return null;
    }
}
