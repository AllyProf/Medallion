<?php

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\SmsService;
use App\Models\SystemSetting;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- MEDALLION SMS TEST ---\n";

$testNumber = '0616775800';
$testMessage = "MEDALLION SYSTEM TEST - Hello! This is a test from the server to verify your SMS credit and connection.";

$smsService = new SmsService();
echo "Testing with: " . $testNumber . "\n";
echo "Message: " . $testMessage . "\n";
echo "Sending...\n";

$result = $smsService->sendSms($testNumber, $testMessage);

echo "--- RESULTS ---\n";
if ($result['success']) {
    echo "STATUS: SUCCESS\n";
} else {
    echo "STATUS: FAILED\n";
}

echo "HTTP CODE: " . ($result['http_code'] ?? 'N/A') . "\n";
echo "RESPONSE: " . ($result['response'] ?? 'N/A') . "\n";
echo "ERROR: " . ($result['error'] ?? 'None') . "\n";

echo "--- END TEST ---\n";
