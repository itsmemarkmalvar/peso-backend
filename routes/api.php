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
    Route::post('/register-request', [App\Http\Controllers\Api\Auth\AuthController::class, 'registerRequest']);
    Route::post('/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [App\Http\Controllers\Api\Auth\AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Invitation routes (public)
Route::prefix('invitation')->group(function () {
    Route::get('/verify', [App\Http\Controllers\Api\Auth\InvitationController::class, 'verify']);
    Route::post('/accept', [App\Http\Controllers\Api\Auth\InvitationController::class, 'accept']);
});

// Test email routes (public - for testing email configuration)
Route::get('/test-email', function (Request $request) {
    try {
        $toEmail = $request->query('email', env('MAIL_FROM_ADDRESS', 'peso.cabuyao19@gmail.com'));
        
        \Illuminate\Support\Facades\Mail::raw('This is a test email from PESO OJT Attendance System. If you received this, your email configuration is working correctly!', function ($message) use ($toEmail) {
            $message->to($toEmail)
                    ->subject('Test Email - PESO OJT Attendance System');
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Test email sent successfully!',
            'sent_to' => $toEmail,
            'from' => env('MAIL_FROM_ADDRESS'),
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send test email',
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Test invitation email route (public - for testing invitation email template)
Route::get('/test-invitation-email', function (Request $request) {
    try {
        $toEmail = $request->query('email', env('MAIL_FROM_ADDRESS', 'peso.cabuyao19@gmail.com'));
        $role = $request->query('role', 'intern');
        
        // Create a mock user object for testing
        $mockUser = (object) [
            'name' => 'Test User',
            'email' => $toEmail,
        ];
        
        // Generate a test invitation URL
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $testToken = 'test-token-' . \Illuminate\Support\Str::random(32);
        $invitationUrl = "{$frontendUrl}/invitation/accept?token={$testToken}";
        
        \Illuminate\Support\Facades\Mail::to($toEmail)->send(
            new App\Mail\InvitationMail($mockUser, $invitationUrl, $role)
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Test invitation email sent successfully!',
            'sent_to' => $toEmail,
            'role' => $role,
            'from' => env('MAIL_FROM_ADDRESS'),
            'note' => 'This is a test email. The invitation link will not work.',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send test invitation email',
            'error' => $e->getMessage(),
        ], 500);
    }
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

    // Leaves
    Route::prefix('leaves')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Leaves\LeaveController::class, 'index']);
        Route::get('/pending', [App\Http\Controllers\Api\Leaves\LeaveController::class, 'pending']);
        Route::post('/', [App\Http\Controllers\Api\Leaves\LeaveController::class, 'store']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Leaves\LeaveController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Leaves\LeaveController::class, 'reject']);
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

    // Departments
    Route::prefix('departments')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\DepartmentsController::class, 'index']);
    });

    // Registration Requests (Admin only) - New system using RegistrationRequest model
    Route::prefix('registration-requests')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\RegistrationRequestsController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\RegistrationRequestsController::class, 'show']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\RegistrationRequestsController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\RegistrationRequestsController::class, 'reject']);
    });

    // Pending Registrations (Admin only) - Legacy system using PendingRegistration model
    Route::prefix('pending-registrations')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\PendingRegistrationsController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\PendingRegistrationsController::class, 'show']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\PendingRegistrationsController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\PendingRegistrationsController::class, 'reject']);
    });
});
