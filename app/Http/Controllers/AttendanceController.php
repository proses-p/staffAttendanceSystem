<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\OfficeLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy' => ['required', 'numeric', 'min:0'],
        ]);

        $latitude = $data['latitude'];
        $longitude = $data['longitude'];
        $accuracy = $data['accuracy'];

        $office = OfficeLocation::first();

        if (!$office) {
            return response()->json([
                'success' => false,
                'message' => 'Office location has not been configured.',
            ], 422);
        }

        $today = Carbon::today();
        //$maxAccuracy = 100;
        // if ($accuracy > $maxAccuracy) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Your location is not accurate enough.',
        //         'accuracy' => round($accuracy, 2),
        //     ], 422);
        // }
        $existingAttendance = Attendance::where(
            'user_id',
            auth()->id()
        )
        ->whereDate('attendance_date', $today)
        ->first();

        if ($existingAttendance) {
            return response()->json([
                'success' => false,
                'message' => 'You have already signed in today.',
            ], 409);
        }

        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            $office->latitude,
            $office->longitude
        );

        if ($distance > $office->allowed_radius) {
            return response()->json([
                'success' => false,
                'message' => 'You are outside the allowed office area.',
                'distance' => round($distance, 2),
            ], 403);
        }

        $attendance = Attendance::create([
            'user_id' => auth()->id(),
            'attendance_date' => $today->toDateString(),
            'check_in_time' => now()->format('H:i:s'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance' => $distance,
            'status' => 'present',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data' => [
                'date' => $attendance->attendance_date->format('d F Y'),
                'day' => $attendance->attendance_date->format('l'),
                'arrival_time' => Carbon::parse(
                    $attendance->check_in_time
                )->format('h:i A'),
                'status' => $attendance->status,
            ],
        ], 201);
    }

    // storing temporarily the location controllerer
    public function updateLocation(Request $request) {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);
        $userId = auth()->id();
        Cache::put(
            "staff_location:{$userId}",
            [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'updated_at' => now()->toDateTimeString(),

            ],
            now()->addMinutes(5)
        );
        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully.',
        ]);
    }


    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $latDifference = deg2rad($latitude2 - $latitude1);
        $lonDifference = deg2rad($longitude2 - $longitude1);

        $a =
            sin($latDifference / 2) ** 2 +
            cos(deg2rad($latitude1)) *
            cos(deg2rad($latitude2)) *
            sin($lonDifference / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}
