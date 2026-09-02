<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OfficeLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm() {
        return view('auth.login');
    }
    public function login(Request $request) {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
            // 'latitude' => [
            //     'required',
            //     'numeric',
            // ],
            // 'longitude' => [
            //     'required',
            //     'numeric',
            // ],
        ]);

        // $latitude = $credentials['latitude'];
        // $longitude = $credentials['longitude'];

        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => 'Invalid email or password',
            ])->onlyInput('email');
        }
        $request->session()->regenerate();
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Your account has been deactivated',
            ]);
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('staff.dashboard');
    //     $office = OfficeLocation::first();
    //     if (!$office) {
    //         Auth::logout();
    //         return back()->withErrors([
    //             'email' => 'Office location has not yet configured',
    //         ]);
    //     }

    //     // checks if the staff member gas sign in today
    //     $today = Carbon::today();
    //     $existingAttendance = Attendance::where(
    //         'user_id',
    //         $user->id
    //     )->whereDate(
    //         'attendance_date',
    //         $today
    //     )->first();

    //     if ($existingAttendance) {
    //         return redirect()->route('staff.dashboard')->with('info', 'You already signed in today.');
    //     }

    //     $distance = $this->calculateDistance(
    //         $latitude,
    //         $longitude,
    //         $office->latitude,
    //         $office->longitude
    //     );

    //     if ($distance > $office->allowed_radius) {
    //         Auth::logout();
    //         return back()->withErrors([
    //             'email' => 'You are not close to the office area, please head foward to sign in',
    //         ]);
    //     }

    //     $attendance = Attendance::create([
    //         'user_id' => $user_id,
    //         'attendance_date' => $today->toDateString(),
    //         'check_in_time' => now()->format('H:i:s'),
    //         'latitude' => $latitude,
    //         'longitude' => $longitude,
    //         'distance' => $distance,
    //         'status' => 'present',
    //     ]);
    //     return redirect()->route('staff.dashboard')->with('attendance_id', $attendance->id);

    // }

    // //  method to calculate the distance
    // private function calculateDistance(
    //     float $latitude1,
    //     float $longitude1,
    //     float $latitude2,
    //     float $longitude2
    // ): float {
    //     $earthRadius = 6371000;
    //     $latitudeDifference = deg2rad(
    //         $latitude2 - $latitude1
    //     );
    //     $longitudeDifference = deg2rad(
    //         $longitude2 - $longitude2
    //     );
    //     $a = sin($latitudeDifference / 2) *
    //          sin($latitudeDifference / 2) +

    //          cos(deg2rad($latitude1)) *
    //          cos(deg2rad($latitude2)) *

    //          sin($longitudeDifference / 2) *
    //          sin($longitudeDifference / 2);

    //     $c = 2 * atan2(
    //         sqrt($a),
    //         sqrt(1 - $a)
    //     );

    //     return $earthRadius * $c;
     }


    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
