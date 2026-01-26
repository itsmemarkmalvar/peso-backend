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

// Debug logo route (public - for testing logo file)
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

// Preview email HTML (public - for testing email template rendering)
Route::get('/preview-invitation-email', function (Request $request) {
    $role = $request->query('role', 'supervisor');
    
    // Create a mock user object for testing
    $mockUser = (object) [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ];
    
    // Generate a test invitation URL
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
    $testToken = 'test-token-' . \Illuminate\Support\Str::random(32);
    $invitationUrl = "{$frontendUrl}/invitation/accept?token={$testToken}";
    
    // Create mail instance to get logo data
    $mail = new App\Mail\InvitationMail($mockUser, $invitationUrl, $role);
    
    // Render the email view
    try {
        $html = view('emails.invitation', [
            'user' => $mockUser,
            'invitationUrl' => $invitationUrl,
            'role' => $role,
            'logoBase64' => $mail->logoBase64,
            'logoPath' => $mail->logoPath,
            // Note: $message is only available when actually sending email via Mail::send()
            // For preview, we rely on base64 which should work
        ])->render();
        
        return response($html)->header('Content-Type', 'text/html');
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
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
        
        // Create mail instance to check logo status
        $mail = new App\Mail\InvitationMail($mockUser, $invitationUrl, $role);
        
        \Illuminate\Support\Facades\Mail::to($toEmail)->send($mail);
        
        // Check logo file status
        $logoPath = public_path('images/image-Photoroom.png');
        $logoExists = file_exists($logoPath);
        $logoBase64Length = 0;
        if ($logoExists) {
            try {
                $logoData = file_get_contents($logoPath);
                $logoBase64Length = strlen(base64_encode($logoData));
            } catch (\Exception $e) {
                // Ignore
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Test invitation email sent successfully!',
            'sent_to' => $toEmail,
            'role' => $role,
            'from' => env('MAIL_FROM_ADDRESS'),
            'logo_status' => [
                'file_exists' => $logoExists,
                'file_path' => $logoPath,
                'base64_encoded' => !empty($mail->logoBase64),
                'base64_length' => $logoBase64Length,
                'logo_path_set' => !empty($mail->logoPath),
            ],
            'note' => 'This is a test email. The invitation link will not work.',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send test invitation email',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
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

    // Geofence Locations
    Route::prefix('geofence-locations')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'show']);
        Route::post('/', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'store']);
        Route::put('/{id}', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\GeofenceLocationsController::class, 'destroy']);
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
