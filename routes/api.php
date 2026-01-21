<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login']);
    Route::post('/register', [App\Http\Controllers\Api\Auth\AuthController::class, 'register']);
    Route::post('/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [App\Http\Controllers\Api\Auth\AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Interns
    Route::prefix('interns')->group(function () {
        Route::get('/me', [App\Http\Controllers\Api\Interns\InternController::class, 'me']);
        Route::post('/me', [App\Http\Controllers\Api\Interns\InternController::class, 'storeProfile']);
        Route::get('/', [App\Http\Controllers\Api\Interns\InternController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Interns\InternController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Interns\InternController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Interns\InternController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Interns\InternController::class, 'destroy']);
    });

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/clock-in', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'clockIn']);
        Route::post('/clock-out', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'clockOut']);
        Route::get('/', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'index']);
        Route::get('/today', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'today']);
        Route::get('/history', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'history']);
        Route::get('/{id}', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'update']);
    });

    // Schedules
    Route::prefix('schedules')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'destroy']);
        Route::post('/assign', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'assign']);
    });

    // Approvals
    Route::prefix('approvals')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Approvals\ApprovalController::class, 'index']);
        Route::get('/pending', [App\Http\Controllers\Api\Approvals\ApprovalController::class, 'pending']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Approvals\ApprovalController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Approvals\ApprovalController::class, 'reject']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/dtr', [App\Http\Controllers\Api\Reports\ReportController::class, 'dtr']);
        Route::get('/attendance', [App\Http\Controllers\Api\Reports\ReportController::class, 'attendance']);
        Route::get('/hours', [App\Http\Controllers\Api\Reports\ReportController::class, 'hours']);
        Route::get('/export', [App\Http\Controllers\Api\Reports\ReportController::class, 'export']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Dashboard\DashboardController::class, 'stats']);
        Route::get('/recent-activity', [App\Http\Controllers\Api\Dashboard\DashboardController::class, 'recentActivity']);
    });
});
