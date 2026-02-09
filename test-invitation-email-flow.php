<?php
/**
 * Step-by-step test: Registration approval → Queued invitation email → Worker sends.
 * Run from backend: php test-invitation-email-flow.php
 * Requires: Laravel backend on http://127.0.0.1:8000, admin user (admin@example.com / Admin123), departments seeded.
 */

$base = 'http://127.0.0.1:8000/api';
$testEmail = 'trace-test-' . time() . '@example.com';

echo "\n=== STEP 1: Create registration request ===\n";
$r1 = post("$base/auth/register-request", ['name' => 'Trace Test User', 'email' => $testEmail]);
echo "Response: " . json_encode($r1, JSON_PRETTY_PRINT) . "\n";
if (empty($r1['data']['id'])) {
    echo "FAIL: No registration request id. Stop.\n";
    exit(1);
}
$requestId = $r1['data']['id'];
echo "OK -> Registration request id = $requestId\n";

echo "\n=== STEP 2: Login as admin ===\n";
$r2 = post("$base/auth/login", ['email' => 'admin@example.com', 'password' => 'Admin123']);
echo "Response: " . (isset($r2['data']['token']) ? 'token received (length=' . strlen($r2['data']['token']) . ')' : json_encode($r2)) . "\n";
if (empty($r2['data']['token'])) {
    echo "FAIL: No token. Run: php artisan db:seed (AdminSeeder).\n";
    exit(1);
}
$token = $r2['data']['token'];
echo "OK -> Token obtained\n";

echo "\n=== STEP 3: List pending registration requests ===\n";
$r3 = get("$base/registration-requests?status=pending", $token);
$list = $r3['data'] ?? [];
echo "Pending count: " . count($list) . "\n";
$found = null;
foreach ($list as $req) {
    if ((int)$req['id'] === (int)$requestId) {
        $found = $req;
        break;
    }
}
if (!$found) {
    echo "FAIL: Our request $requestId not in list.\n";
    exit(1);
}
echo "OK -> Request $requestId found (email: {$found['email']})\n";

echo "\n=== STEP 4: Approve registration (queues invitation email) ===\n";
$r4 = post("$base/registration-requests/$requestId/approve", [
    'role' => 'intern',
    'department_id' => 1,
], $token);
echo "Response: " . json_encode($r4, JSON_PRETTY_PRINT) . "\n";
if (empty($r4['success']) || $r4['success'] !== true) {
    echo "FAIL: Approval failed.\n";
    exit(1);
}
echo "OK -> Approval succeeded. Invitation email QUEUED.\n";

echo "\n=== STEP 5: Check jobs table (run in MySQL or tinker) ===\n";
echo "Run: php artisan tinker --execute=\"echo \\App\\Jobs\\SendQueuedMailable::class; echo count(DB::table('jobs')->get());\"\n";
echo "Or: SELECT id, queue, created_at FROM jobs ORDER BY id DESC LIMIT 3;\n";
echo "Then run: php artisan queue:work (process one job)\n";

echo "\n=== STEP 6: After queue:work ===\n";
echo "If MAIL_MAILER=log: check storage/logs/laravel.log for the invitation email.\n";
echo "If MAIL_MAILER=smtp: check inbox for $testEmail (or MAIL_FROM_ADDRESS).\n";

echo "\n=== DONE ===\n";

function get($url, $token = null) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" . ($token ? "Authorization: Bearer $token\r\n" : ''),
        ],
    ]);
    $r = @file_get_contents($url, false, $ctx);
    return $r ? json_decode($r, true) : [];
}

function post($url, $body, $token = null) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n" . ($token ? "Authorization: Bearer $token\r\n" : ''),
            'content' => json_encode($body),
        ],
    ]);
    $r = @file_get_contents($url, false, $ctx);
    return $r ? json_decode($r, true) : [];
}
