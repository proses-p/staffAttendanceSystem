<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FaceEnrollmentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StaffRegistrationController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company.active'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/v1/login', [AuthController::class, 'login']);
Route::post('/v1/staff/register', [StaffRegistrationController::class, 'complete'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['company.active'])->group(function () {
        Route::get('/user', [ProfileController::class, 'show']);
        Route::put('/user', [ProfileController::class, 'update']);
        Route::put('/user/password', [ProfileController::class, 'changePassword'])
            ->middleware('throttle:5,1');
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('role:staff');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->middleware('role:staff');
        Route::post('/attendance/location', [AttendanceController::class, 'updateLocation'])->middleware('role:staff');
        Route::get('/attendance/history', [AttendanceController::class, 'history'])->middleware('role:staff');
        Route::get('/dashboard', [AttendanceController::class, 'getDashboardData'])->middleware('role:staff');
        Route::get('/status', [FaceEnrollmentController::class, 'status'])->middleware('role:staff');
        Route::post('/enroll', [FaceEnrollmentController::class, 'enroll'])->middleware('role:staff');
    });
});
