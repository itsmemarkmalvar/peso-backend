<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Only in local: check which DB this Laravel app is connected to (for shared DB troubleshooting)
if (app()->environment('local')) {
    Route::get('/db-check', function () {
        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        try {
            $userCount = \Illuminate\Support\Facades\DB::table('users')->count();
        } catch (\Throwable $e) {
            $userCount = 'error: ' . $e->getMessage();
        }
        $registrationRequests = 'not checked';
        try {
            $registrationRequests = \Illuminate\Support\Facades\DB::table('registration_requests')->count();
        } catch (\Throwable $e) {
            $registrationRequests = 'error: ' . $e->getMessage();
        }
        return response()->json([
            'message' => 'This Laravel app is using:',
            'DB_HOST' => $host,
            'DB_DATABASE' => $database,
            'users_count' => $userCount,
            'registration_requests_count' => $registrationRequests,
            'expected_for_shared' => $host === '192.168.254.103' ? 'YES - connected to shared host' : 'NO - using local or wrong host',
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });
}
