<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\OfficeLocationController;
use App\Http\Controllers\Admin\StaffInvitationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StaffController;
use App\Mail\NearOfficeMail;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    //return redirect()->route('login');
    //return view('welcome');
    if (!auth()->check()) {
        return redirect()->route('login');
    }
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('staff.dashboard');
})->name('home');

// login routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.store');
Route::match(['get', 'post'], '/staff/register/{token}', [StaffInvitationController::class, 'register'])->name('staff.register');

Route::middleware('auth')->group(function () {

    // admin dashboard routes
    Route::get('/admin/dashboard', [
        AdminDashboardController::class, 'index'
    ])->middleware('role:admin')->name('admin.dashboard');
    Route::get('/admin/attendance', [AttendanceReportController::class, 'index'])->middleware('role:admin')->name('admin.attendance.index');
    Route::post('/admin/office-location', [OfficeLocationController::class, 'store'])->middleware('role:admin')->name('admin.office-location.store');
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/time-settings', [AttendanceSettingController::class, 'index'])->name('admin.time-settings.index');
        Route::post('/admin/time-settings', [AttendanceSettingController::class, 'store'])->name('admin.time-settings.store');
        Route::post('/admin/invitations', [StaffInvitationController::class, 'store'])->name('admin.invitations.store');
    });

    // staff dashboard routes
    Route::get('/staff/dashboard', function () {
        $user = auth()->user();
        $records = Attendance::where('user_id', $user->id)
            ->orderByDesc('attendance_date')
            ->paginate(6);
        $attendance = $records->first(fn ($record) => $record->attendance_date->isToday());

        // gets the all attendance of the month
        $monthRecords = Attendance::where('user_id', $user->id)
            ->whereBetween('attendance_date', [today()->startOfMonth(), today()->endOfMonth()])
            ->get();
        $completed = $records->filter(fn ($record) => $record->check_in_time && $record->check_out_time);
        $workingMinutes = $completed->map(fn ($record) => Carbon::parse($record->check_in_time)->diffInMinutes(Carbon::parse($record->check_out_time)));
        $monthStart = today()->startOfMonth();

        $weekdaysElapsed = collect(range(0, today()->diffInDays($monthStart)))
        ->filter(fn ($day) => !$monthStart->copy()->addDays($day)->isWeekend())
        ->count();
        // $weekdaysElapsed = collect(range(0, today()->diffInDays($monthStart)))->filter(fn ($day) => !$monthStart->copy()->addDays($day)->isWeekend())->count();
        $expectedArrival = AttendanceSetting::first()?->expected_arrival_time;
        // $monthRecords = $records->filter(fn ($record) => $record->attendance_date->isCurrentMonth());
        // $onTimeCount = $monthRecords->filter(fn ($record) => $expectedArrival && $record->check_in_time && Carbon::parse($record->check_in_time)->format('H:i') <= Carbon::parse($expectedArrival)->format('H:i'))->count();
        $onTimeCount = $monthRecords->filter(fn ($record) => 
        $expectedArrival && 
        $record->check_in_time && 
        Carbon::parse($record->check_in_time)->format('H:i') <= Carbon::parse($expectedArrival)->format('H:i')
        )->count();

        return view('staff.dashboard', [
            'attendance' => $attendance,
            'records' => $records,
            'stats' => [
                'today_minutes' => $attendance?->check_in_time && $attendance?->check_out_time ? Carbon::parse($attendance->check_in_time)->diffInMinutes(Carbon::parse($attendance->check_out_time)) : 0,
                'average_minutes' => $workingMinutes->count() ? round($workingMinutes->avg()) : 0,
                'attendance_rate' => $weekdaysElapsed ? min(100, round(($monthRecords->count() / $weekdaysElapsed) * 100)) : 0,
                'on_time_rate' => $monthRecords->count() ? round(($onTimeCount / $monthRecords->count()) * 100) : 0,
            ],
        ]);
    })->middleware('role:staff')->name('staff.dashboard');

    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('staff/location', [AttendanceController::class, 'updateLocation'])->name('staff.location.update');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

    // staff management routes
    Route::resource('/admin/staff', StaffController::class)->middleware('role:admin');


    // logout route
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

});

Route::get('/test-near-office-email', function () {
    Mail::to('prosesprojestus0@gmail.com')
        ->send(new NearOfficeMail('Test staff'));
    return 'Near office email sent successfully.';
});


