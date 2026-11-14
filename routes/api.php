<?php

use App\Http\Controllers\Api\AdminFieldExpenseController;
use App\Http\Controllers\Api\AdminFieldOperationsController;
use App\Http\Controllers\Api\AnnouncementApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\DailyWorkSubmissionController;
use App\Http\Controllers\Api\DeviceSecurityController;
use App\Http\Controllers\Api\FaceEnrollmentController;
use App\Http\Controllers\Api\FieldDashboardController;
use App\Http\Controllers\Api\FieldExpenseController;
use App\Http\Controllers\Api\FieldOperationsController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\MyPayslipController;
use App\Http\Controllers\Api\SalaryAdvanceController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StaffAiAssistantController;
use App\Http\Controllers\Api\StaffDeviceController;
use App\Http\Controllers\Api\StaffRegistrationController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company.active'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/v1/login', [AuthController::class, 'login']);
Route::post('/v1/staff/register', [StaffRegistrationController::class, 'complete'])->middleware('throttle:5,1');
Route::post('/v1/auth/forgot-password', [PasswordResetController::class, 'sendCode'])
    ->middleware('throttle:5,1');
Route::post('/v1/auth/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:5,1');
Route::get('/time', [FieldOperationsController::class, 'time'])->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['company.active'])->group(function () {
        Route::post('/ai/staff-assistant', [StaffAiAssistantController::class, 'ask'])
            ->middleware(['role:staff', 'throttle:20,1']);
        Route::post('/ai/draft-work-report', [StaffAiAssistantController::class, 'draftReport'])
            ->middleware(['role:staff', 'throttle:20,1']);
        Route::get('/announcements', [AnnouncementApiController::class, 'index'])
            ->middleware('role:staff');
        Route::post('/announcements/{id}/read', [AnnouncementApiController::class, 'markRead'])
            ->whereNumber('id')->middleware('role:staff');
        Route::get('/my-payslip', [MyPayslipController::class, 'show'])->middleware('role:staff');
        Route::post('/salary-advances', [SalaryAdvanceController::class, 'store'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::get('/chat/group', [ChatApiController::class, 'group']);
        Route::post('/chat/group', [ChatApiController::class, 'sendGroup']);
        Route::put('/chat/group/{id}', [ChatApiController::class, 'updateGroup'])->whereNumber('id');
        Route::delete('/chat/group/{id}', [ChatApiController::class, 'destroyGroup'])->whereNumber('id');
        Route::get('/chat/private/conversations', [ChatApiController::class, 'conversations']);
        Route::get('/chat/private/{colleagueId}', [ChatApiController::class, 'privateHistory'])
            ->whereNumber('colleagueId');
        Route::post('/chat/private/{colleagueId}', [ChatApiController::class, 'sendPrivate'])
            ->whereNumber('colleagueId');
        Route::put('/chat/private/message/{id}', [ChatApiController::class, 'updatePrivate'])
            ->whereNumber('id');
        Route::delete('/chat/private/message/{id}', [ChatApiController::class, 'destroyPrivate'])
            ->whereNumber('id');
        Route::get('/notifications/unread', [NotificationApiController::class, 'fetchUnread']);
        Route::get('/user', [ProfileController::class, 'show']);
        Route::put('/user', [ProfileController::class, 'update']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/user/password', [ProfileController::class, 'changePassword'])
            ->middleware('throttle:5,1');
        Route::post('/attendance/challenges', [StaffDeviceController::class, 'attendanceChallenge'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::post('/attendance/location', [AttendanceController::class, 'updateLocation'])->middleware('role:staff');
        Route::get('/attendance/history', [AttendanceController::class, 'history'])->middleware('role:staff');
        Route::get('/dashboard', [AttendanceController::class, 'getDashboardData'])->middleware('role:staff');
        Route::get('/field/status', [FieldOperationsController::class, 'status'])->middleware('role:staff');
        Route::post('/field/start', [FieldOperationsController::class, 'start'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::post('/field/end', [FieldOperationsController::class, 'end'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::get('/field/active', [FieldOperationsController::class, 'active'])->middleware('role:staff');
        Route::get('/field/expenses', [FieldExpenseController::class, 'index']);
        Route::post('/field/expenses', [FieldExpenseController::class, 'store'])
            ->middleware(['role:staff', 'throttle:10,1']);
        Route::get('/field/expenses/{expense}', [FieldExpenseController::class, 'show'])
            ->whereNumber('expense');
        Route::get('/field/expenses/{expense}/receipt', [FieldExpenseController::class, 'receipt'])
            ->whereNumber('expense');
        Route::post('/sync/batch', [FieldOperationsController::class, 'sync'])
            ->middleware(['role:staff', 'throttle:30,1']);
        Route::post('/sync/challenge', [StaffDeviceController::class, 'fieldSyncChallenge'])
            ->middleware(['role:staff', 'throttle:30,1']);
        Route::get('/dashboard/employee', [FieldDashboardController::class, 'employee'])
            ->middleware('role:staff');
        Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->middleware('role:staff');
        Route::post('/leave-requests', [LeaveRequestController::class, 'store'])
            ->middleware(['role:staff', 'throttle:5,1']);
        Route::get('/status', [FaceEnrollmentController::class, 'status'])->middleware('role:staff');
        Route::post('/enroll', [FaceEnrollmentController::class, 'enroll'])->middleware('role:staff');
        Route::post('/devices/enrollment-challenge', [StaffDeviceController::class, 'enrollmentChallenge'])
            ->middleware(['role:staff', 'throttle:5,1']);
        Route::post('/devices/enroll', [StaffDeviceController::class, 'enroll'])
            ->middleware(['role:staff', 'throttle:5,1']);
        Route::get('/devices/status', [StaffDeviceController::class, 'status'])->middleware('role:staff');
        Route::post('/device/sync-recovered-key', [DeviceSecurityController::class, 'syncRecoveredKey'])
            ->middleware(['role:staff', 'throttle:3,1']);
        Route::get('/daily-work/status', [DailyWorkSubmissionController::class, 'status'])
            ->middleware('role:staff');
        Route::post('/daily-work/submit', [DailyWorkSubmissionController::class, 'submit'])
            ->middleware(['role:staff', 'throttle:5,1']);
    });

    Route::middleware(['company.active', 'can:admin-oversight'])->prefix('admin')->group(function () {
        Route::get('/field-expenses', [AdminFieldExpenseController::class, 'index']);
        Route::get('/field-expenses/{expense}/receipt', [AdminFieldExpenseController::class, 'receipt'])
            ->whereNumber('expense');
        Route::get('/field-expenses/{expense}', [AdminFieldExpenseController::class, 'show'])
            ->whereNumber('expense');
        Route::post('/field-expenses/{expense}/approve', [AdminFieldExpenseController::class, 'approve'])
            ->whereNumber('expense');
        Route::post('/field-expenses/{expense}/reject', [AdminFieldExpenseController::class, 'reject'])
            ->whereNumber('expense');
        Route::get('/dashboard', [AdminFieldOperationsController::class, 'dashboard']);
        Route::get('/field/on-field', [AdminFieldOperationsController::class, 'onField']);
        Route::get('/field/flagged', [AdminFieldOperationsController::class, 'flagged']);
        Route::post('/field/{fieldAssignment}/review', [AdminFieldOperationsController::class, 'review']);
        Route::get('/attendance/missing', [AdminFieldOperationsController::class, 'missing']);
    });
});
