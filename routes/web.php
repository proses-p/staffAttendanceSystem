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
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('attendance_date', today())
            ->first();
        return view('staff.dashboard', compact('attendance'));
    })->middleware('role:staff')->name('staff.dashboard');

    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('staff/location', [AttendanceController::class, 'updateLocation'])->name('staff.location.update');

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


