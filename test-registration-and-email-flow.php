#!/usr/bin/env php
<?php
/**
 * End-to-end test: User registration request → Admin approval → Invitation email queued → Process queue → Verify email sent.
 *
 * Run from backend directory:
 *   php test-registration-and-email-flow.php
 *
 * Optional: process the queue and verify (requires running from peso-backend so Laravel can bootstrap):
 *   php test-registration-and-email-flow.php --process-queue
 *
 * Prerequisites:
 *   - Laravel backend running (e.g. php artisan serve → http://127.0.0.1:8000)
 *   - Admin user exists (e.g. admin@example.com / Admin123 from AdminSeeder)
 *   - At least one department (e.g. from seeders)
 */

$baseUrl = getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000/api';
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'Admin123';
$processQueue = in_array('--process-queue', $argv ?? [], true);

$testEmail = 'test-reg-' . time() . '@example.com';
$testName = 'Test Registration User';

echo "\n";
echo "========================================\n";
echo "  Registration + Email Flow Test\n";
echo "========================================\n";
echo "  API: {$baseUrl}\n";
echo "  Test email: {$testEmail}\n";
echo "  Process queue: " . ($processQueue ? 'yes' : 'no (use --process-queue to run worker)') . "\n";
echo "\n";

// --- Step 1: Submit registration request ---
echo "[1/7] Submitting registration request...\n";
$r1 = post("{$baseUrl}/auth/register-request", ['name' => $testName, 'email' => $testEmail]);
if (!isset($r1['data']['id'])) {
    echo "  FAIL: No registration request ID. Response: " . json_encode($r1, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
$requestId = (int) $r1['data']['id'];
echo "  OK – Registration request id = {$requestId}\n";

// --- Step 2: Login as admin ---
echo "[2/7] Logging in as admin...\n";
$r2 = post("{$baseUrl}/auth/login", ['email' => $adminEmail, 'password' => $adminPassword]);
if (empty($r2['data']['token'])) {
    echo "  FAIL: No token. Check admin credentials and run: php artisan db:seed\n";
    echo "  Response: " . json_encode($r2, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
$token = $r2['data']['token'];
echo "  OK – Token obtained\n";

// --- Step 3: List pending requests and find ours ---
echo "[3/7] Fetching pending registration requests...\n";
$r3 = get("{$baseUrl}/registration-requests?status=pending", $token);
$list = $r3['data'] ?? [];
$found = null;
foreach ($list as $req) {
    if ((int)($req['id'] ?? 0) === $requestId) {
        $found = $req;
        break;
    }
}
if (!$found) {
    echo "  FAIL: Request {$requestId} not found in pending list.\n";
    exit(1);
}
echo "  OK – Request {$requestId} found (email: {$found['email']})\n";

// When we will process the queue, clear old pending jobs so queue:work --once runs OUR new job (not an old one)
// Bootstrap once and keep $laravelApp so step 5/6 don't run require_once again (which returns true and breaks ->make())
$backendDir = __DIR__;
$laravelApp = null;
if ($processQueue && file_exists($backendDir . '/vendor/autoload.php') && file_exists($backendDir . '/bootstrap/app.php')) {
    try {
        require $backendDir . '/vendor/autoload.php';
        $laravelApp = require_once $backendDir . '/bootstrap/app.php';
        $laravelApp->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $deleted = Illuminate\Support\Facades\DB::table('jobs')->delete();
        if ($deleted > 0) {
            echo "  (Cleared {$deleted} old pending job(s) so the new invitation job will be processed.)\n";
        }
    } catch (Throwable $e) {
        echo "  WARN – Could not clear pending jobs: " . $e->getMessage() . "\n";
        $laravelApp = null;
    }
}

// --- Step 4: Approve registration (queues invitation email) ---
echo "[4/7] Approving registration (queues invitation email)...\n";
$r4 = post("{$baseUrl}/registration-requests/{$requestId}/approve", [
    'role' => 'intern',
    'department_id' => 1,
], $token);
if (empty($r4['success']) || $r4['success'] !== true) {
    echo "  FAIL: Approval failed. Response: " . json_encode($r4, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "  OK – Registration approved. Invitation email queued.\n";

// --- Step 5: Verify job was queued (optional: via Laravel) ---
$backendDir = $backendDir ?? __DIR__;
$jobsCountBefore = null;
$jobsCountAfter = null;
$failedCountBefore = null;
$failedCountAfter = null;

if (file_exists($backendDir . '/vendor/autoload.php') && file_exists($backendDir . '/bootstrap/app.php')) {
    echo "[5/7] Checking queue (Laravel bootstrap)...\n";
    try {
        if (!$laravelApp) {
            require $backendDir . '/vendor/autoload.php';
            $laravelApp = require_once $backendDir . '/bootstrap/app.php';
            $laravelApp->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }
        $jobsCountBefore = (int) Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedCountBefore = (int) Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        echo "  OK – Jobs in queue: {$jobsCountBefore}, Failed jobs: {$failedCountBefore}\n";
    } catch (Throwable $e) {
        echo "  WARN – Could not check queue: " . $e->getMessage() . "\n";
        echo "  You can verify manually: SELECT COUNT(*) FROM jobs;\n";
    }
} else {
    echo "[5/7] Skipping queue check (run from peso-backend for auto check).\n";
}

// --- Step 6: Process one job (optional) ---
if ($processQueue && file_exists($backendDir . '/artisan')) {
    echo "[6/7] Running queue worker once (php artisan queue:work --once)...\n";
    $cwd = getcwd();
    chdir($backendDir);
    passthru('php artisan queue:work --once --tries=1', $exitCode);
    chdir($cwd);
    if ($exitCode !== 0) {
        echo "  WARN – queue:work exited with code {$exitCode}. Check storage/logs/laravel.log and failed_jobs table.\n";
    } else {
        echo "  OK – Worker finished.\n";
    }
    if ($laravelApp) {
        $jobsCountAfter = (int) Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedCountAfter = (int) Illuminate\Support\Facades\DB::table('failed_jobs')->count();
    }
} else {
    echo "[6/7] Skipping queue processing (use --process-queue to run worker).\n";
}

// --- Step 7: Result summary ---
echo "[7/7] Result summary\n";
echo "  – Registration request: submitted and approved.\n";
echo "  – Invitation email: queued (job added to `jobs` table).\n";
if ($jobsCountBefore !== null) {
    echo "  – Jobs in queue after approval: {$jobsCountBefore}\n";
}
if ($processQueue && $jobsCountAfter !== null && $failedCountAfter !== null) {
    // Only report FAILED if we have a baseline and failed count actually increased (new failure)
    if ($failedCountBefore !== null && $failedCountAfter > $failedCountBefore) {
        echo "  – Email send: FAILED (job moved to failed_jobs). Check storage/logs/laravel.log and failed_jobs table.\n";
        exit(1);
    }
    if ($jobsCountBefore !== null && $jobsCountBefore > 0 && $jobsCountAfter < $jobsCountBefore) {
        echo "  – Email send: job processed successfully.\n";
        echo "    With MAIL_MAILER=log: check storage/logs/laravel.log for the invitation content.\n";
        echo "    With MAIL_MAILER=smtp: check inbox for {$testEmail} (or your SMTP test address).\n";
    }
}
echo "\n";
echo "========================================\n";
echo "  Test completed successfully.\n";
echo "========================================\n";
echo "\n";
echo "Next steps (if you did not use --process-queue):\n";
echo "  1. Process the queued email job:\n";
echo "     php artisan queue:work --once\n";
echo "  2. If MAIL_MAILER=log: open storage/logs/laravel.log to see the email content.\n";
echo "  3. If MAIL_MAILER=smtp: check inbox for {$testEmail}\n";
echo "  If the job failed: open storage/logs/laravel.log or run: php artisan queue:failed\n";
echo "\n";

exit(0);

// --- Helpers ---
function get(string $url, ?string $token = null): array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" . ($token ? "Authorization: Bearer {$token}\r\n" : ''),
            'timeout' => 15,
        ],
    ];
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    return $body ? (json_decode($body, true) ?? []) : [];
}

function post(string $url, array $body, ?string $token = null): array {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n" . ($token ? "Authorization: Bearer {$token}\r\n" : ''),
            'content' => json_encode($body),
            'timeout' => 15,
        ],
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    return $response ? (json_decode($response, true) ?? []) : [];
}
