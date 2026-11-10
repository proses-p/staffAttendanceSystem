<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\FieldExpenseReviewController;
use App\Http\Controllers\Admin\LeaveManagementController;
use App\Http\Controllers\Admin\OfficeLocationController;
use App\Http\Controllers\Admin\StaffDeviceController as AdminStaffDeviceController;
use App\Http\Controllers\Admin\StaffInvitationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PdfDevelopmentController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\StaffController;
// use App\Http\Controllers\AdminSearchController;
use App\Mail\NearOfficeMail;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return redirect()->route('login');
    // return view('welcome');
    if (! auth()->check()) {
        return redirect()->route('login');
    }
    if (auth()->user()->role === 'super_admin') {
        return redirect()->route('platform.dashboard');
    }
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    if (auth()->user()->role === 'manager') {
        return redirect()->route('admin.field-expenses.index');
    }

    return redirect()->route('staff.dashboard');
})->name('home');

// login routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.store');
Route::match(['get', 'post'], '/staff/register/{token}', [StaffInvitationController::class, 'register'])->name('staff.register');

Route::middleware('auth')->group(function () {
    Route::prefix('platform')
        ->name('platform.')
        ->middleware(['role:super_admin', 'company.active'])
        ->group(function () {
            Route::get('/', [CompanyController::class, 'dashboard'])->name('dashboard');
            Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
            Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
            Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
            Route::patch('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
            Route::post('/companies/{company}/admin-invitations', [CompanyController::class, 'inviteAdmin'])
                ->name('companies.admin-invitations.store');
        });

    Route::middleware(['role:admin', 'company.active'])->group(function () {
        Route::get('/admin/dashboard', [
            AdminDashboardController::class, 'index',
        ])->name('admin.dashboard');
        Route::get('/admin/attendance', [AttendanceReportController::class, 'index'])->name('admin.attendance.index');
        Route::post('/admin/office-location', [OfficeLocationController::class, 'store'])->name('admin.office-location.store');

        Route::get('/admin/time-settings', [AttendanceSettingController::class, 'index'])->name('admin.time-settings.index');
        Route::post('/admin/time-settings', [AttendanceSettingController::class, 'store'])->name('admin.time-settings.store');
        Route::post('/admin/holidays', [AttendanceSettingController::class, 'storeHoliday'])->name('admin.holidays.store');
        Route::delete('/admin/holidays/{holiday}', [AttendanceSettingController::class, 'destroyHoliday'])->name('admin.holidays.destroy');
        Route::get('/admin/leaves', [LeaveManagementController::class, 'index'])->name('admin.leaves.index');
        Route::post('/admin/leave-records', [LeaveManagementController::class, 'store'])->name('admin.leave-records.store');
        Route::patch('/admin/leave-records/{leaveRecord}', [LeaveManagementController::class, 'update'])->name('admin.leave-records.update');
        Route::post('/admin/invitations', [StaffInvitationController::class, 'store'])->name('admin.invitations.store');
        Route::get('/admin/attendance/report', [PdfDevelopmentController::class, 'download'])->name('admin.attendance.report');
        // Route::get('/admin/attendance/search', [AdminSearchController::class, 'index'])->name('admin.attendance.search');

        Route::resource('/admin/staff', StaffController::class)->names('staff');
    });

    Route::middleware(['role:admin', 'company.active'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/devices', [AdminStaffDeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices/{device}/approve', [AdminStaffDeviceController::class, 'approve'])->name('devices.approve');
        Route::post('/devices/{device}/revoke', [AdminStaffDeviceController::class, 'revoke'])->name('devices.revoke');
    });

    Route::middleware(['can:admin-oversight', 'company.active'])
        ->prefix('admin/field-expenses')
        ->name('admin.field-expenses.')
        ->group(function () {
            Route::get('/', [FieldExpenseReviewController::class, 'index'])->name('index');
            Route::get('/{expense}', [FieldExpenseReviewController::class, 'show'])->whereNumber('expense')->name('show');
            Route::get('/{expense}/receipt', [FieldExpenseReviewController::class, 'receipt'])
                ->whereNumber('expense')
                ->name('receipt');
            Route::post('/{expense}/approve', [FieldExpenseReviewController::class, 'approve'])
                ->whereNumber('expense')
                ->name('approve');
            Route::post('/{expense}/reject', [FieldExpenseReviewController::class, 'reject'])
                ->whereNumber('expense')
                ->name('reject');
        });

    // staff dashboard routes
    Route::middleware(['role:staff', 'company.active'])->group(function () {
        Route::get('/staff/dashboard', function () {
            $user = auth()->user();
            $records = Attendance::where('user_id', $user->id)
                ->where('company_id', $user->company_id)
                ->orderByDesc('attendance_date')
                ->paginate(6);
            $attendance = $records->first(fn ($record) => $record->attendance_date->isToday());

            // gets the all attendance of the month
            $monthRecords = Attendance::where('user_id', $user->id)
                ->where('company_id', $user->company_id)
                ->whereBetween('attendance_date', [today()->startOfMonth(), today()->endOfMonth()])
                ->get();
            $completed = $records->filter(fn ($record) => $record->check_in_time && $record->check_out_time);
            $workingMinutes = $completed->map(fn ($record) => Carbon::parse($record->check_in_time)->diffInMinutes(Carbon::parse($record->check_out_time)));
            $monthStart = today()->startOfMonth();

            $weekdaysElapsed = collect(range(0, today()->diffInDays($monthStart)))
                ->filter(fn ($day) => ! $monthStart->copy()->addDays($day)->isWeekend())
                ->count();
            // $weekdaysElapsed = collect(range(0, today()->diffInDays($monthStart)))->filter(fn ($day) => !$monthStart->copy()->addDays($day)->isWeekend())->count();
            $expectedArrival = AttendanceSetting::where(
                'company_id',
                $user->company_id
            )->first()?->expected_arrival_time;
            // $monthRecords = $records->filter(fn ($record) => $record->attendance_date->isCurrentMonth());
            // $onTimeCount = $monthRecords->filter(fn ($record) => $expectedArrival && $record->check_in_time && Carbon::parse($record->check_in_time)->format('H:i') <= Carbon::parse($expectedArrival)->format('H:i'))->count();
            $onTimeCount = $monthRecords->filter(fn ($record) => $expectedArrival &&
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
        })->name('staff.dashboard');

        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('staff/location', [AttendanceController::class, 'updateLocation'])->name('staff.location.update');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    });

    // logout route
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

});

Route::get('/test-near-office-email', function () {
    Mail::to('prosesprojestus0@gmail.com')
        ->send(new NearOfficeMail('Test staff'));

    return 'Near office email sent successfully.';
});
