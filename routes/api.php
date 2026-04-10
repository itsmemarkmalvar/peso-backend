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

// Public routes (throttle on public auth/invitation to reduce brute-force and abuse)
Route::prefix('auth')->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [App\Http\Controllers\Api\Auth\AuthController::class, 'register'])->middleware('throttle:20,1');
    Route::post('/register-request', [App\Http\Controllers\Api\Auth\AuthController::class, 'registerRequest'])->middleware('throttle:20,1');
    Route::post('/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [App\Http\Controllers\Api\Auth\AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::prefix('invitation')->group(function () {
    Route::get('/verify', [App\Http\Controllers\Api\Auth\InvitationController::class, 'verify'])->middleware('throttle:30,1');
    Route::post('/accept', [App\Http\Controllers\Api\Auth\InvitationController::class, 'accept'])->middleware('throttle:30,1');
});

// Debug routes: only registered when not in production (avoid exposing paths/previews)
if (! app()->environment('production')) {
    Route::get('/test-logo', function () {
        $logoPath = public_path('images/image-Photoroom.png');
        $logoPath2 = base_path('public/images/image-Photoroom.png');

        $logoBase64 = null;
        if (file_exists($logoPath)) {
            try {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return response()->json([
            'public_path' => $logoPath,
            'base_path' => $logoPath2,
            'exists_public' => file_exists($logoPath),
            'exists_base' => file_exists($logoPath2),
            'public_path_resolved' => realpath($logoPath) ?: 'not found',
            'base_path_resolved' => realpath($logoPath2) ?: 'not found',
            'base64_length' => file_exists($logoPath) ? strlen(base64_encode(file_get_contents($logoPath))) : 0,
            'base64_preview' => $logoBase64 ? substr($logoBase64, 0, 100) . '...' : 'not generated',
            'file_size' => file_exists($logoPath) ? filesize($logoPath) : 0,
        ]);
    });

    Route::get('/preview-invitation-email', function (Request $request) {
        $role = $request->query('role', 'supervisor');

        $mockUser = (object) [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $testToken = 'test-token-' . \Illuminate\Support\Str::random(32);
        $invitationUrl = "{$frontendUrl}/invitation/accept?token={$testToken}";

        $mail = new App\Mail\InvitationMail(0, $invitationUrl, $role);

        try {
            $html = view('emails.invitation', [
                'user' => $mockUser,
                'invitationUrl' => $invitationUrl,
                'role' => $role,
                'logoBase64' => $mail->logoBase64,
                'logoPath' => $mail->logoPath,
            ])->render();

            return response($html)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    });
}

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Interns
    Route::prefix('interns')->group(function () {
        Route::get('/me', [App\Http\Controllers\Api\Interns\InternController::class, 'me']);
        Route::get('/me/resume', [App\Http\Controllers\Api\Interns\InternController::class, 'viewMyResume']);
        Route::post('/me', [App\Http\Controllers\Api\Interns\InternController::class, 'storeProfile']);
        Route::get('/', [App\Http\Controllers\Api\Interns\InternController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Interns\InternController::class, 'store']);
        Route::get('/{id}/resume', [App\Http\Controllers\Api\Interns\InternController::class, 'viewResume']);
        Route::get('/{id}', [App\Http\Controllers\Api\Interns\InternController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Interns\InternController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Interns\InternController::class, 'destroy']);
    });

    // Intern Dashboard
    Route::prefix('intern')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\Interns\InternDashboardController::class, 'dashboard']);
        Route::get('/time-clock', [App\Http\Controllers\Api\Interns\InternDashboardController::class, 'timeClock']);
        Route::get('/timesheets', [App\Http\Controllers\Api\TimesheetsController::class, 'internWeekly']);
        Route::get('/approvals', [App\Http\Controllers\Api\Interns\InternDashboardController::class, 'approvals']);
    });

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/clock-in', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'clockIn']);
        Route::post('/clock-in-correction', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'clockInCorrection']);
        Route::post('/clock-out', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'clockOut']);
        Route::post('/break-start', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'breakStart']);
        Route::post('/break-end', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'breakEnd']);
        Route::get('/', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'index']);
        Route::get('/today', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'today']);
        Route::get('/today-all', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'todayAll']);
        Route::get('/live-locations', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'liveLocations']);
        Route::get('/approved-hours-summary', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'approvedHoursSummary']);
        Route::get('/history', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'history']);
        Route::get('/{id}', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Attendance\AttendanceController::class, 'update']);
    });

    // Schedules
    Route::prefix('schedules')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'index']);
        Route::get('/default', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'defaultSchedule']);
        Route::post('/', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'store']);
        Route::get('/excused', [App\Http\Controllers\Api\Schedules\ScheduleController::class, 'excused']);
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

    // Timesheets
    Route::prefix('timesheets')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\TimesheetsController::class, 'index']);
        Route::get('/{intern_id}', [App\Http\Controllers\Api\TimesheetsController::class, 'show']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Dashboard\DashboardController::class, 'stats']);
        Route::get('/recent-activity', [App\Http\Controllers\Api\Dashboard\DashboardController::class, 'recentActivity']);
    });

    // Admin filter options (roles from UserRole enum, groups from distinct company_name)
    Route::get('/admin/filter-options', [App\Http\Controllers\Api\FilterOptionsController::class, 'index']);

    // Departments
    Route::prefix('departments')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\DepartmentsController::class, 'index']);
        Route::get('/{id}/supervisors', [App\Http\Controllers\Api\DepartmentsController::class, 'supervisors']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\NotificationsController::class, 'index']);
        Route::post('/{id}/read', [App\Http\Controllers\Api\NotificationsController::class, 'markRead']);
        Route::post('/{id}/respond', [App\Http\Controllers\Api\NotificationsController::class, 'respond']);
    });

    // Documents (Admin uploads, interns view)
    Route::prefix('documents')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\DocumentsController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\DocumentsController::class, 'store']);
        Route::get('/{id}/download', [App\Http\Controllers\Api\DocumentsController::class, 'download']);
        Route::delete('/{id}', [App\Http\Controllers\Api\DocumentsController::class, 'destroy']);
    });

    // Geofence Locations
    Route::prefix('geofence-locations')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'show']);
        Route::post('/', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'store']);
        Route::put('/{id}', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'destroy']);
    });

    // Settings (GET: any authenticated user; PUT: admin only, enforced in controller)
    Route::get('/settings', [App\Http\Controllers\Api\SettingsController::class, 'index']);
    Route::put('/settings', [App\Http\Controllers\Api\SettingsController::class, 'update']);

    // Registration Requests (Admin only) - New system using RegistrationRequest model
    Route::prefix('registration-requests')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\RegistrationRequestsController::class, 'index']);
        Route::get('/approved-users', [App\Http\Controllers\Api\RegistrationRequestsController::class, 'approvedUsers']);
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
