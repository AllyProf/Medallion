<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;
use App\Models\FinancialHandover;
use App\Services\HandoverSmsService;

$ownerId = 4;
$date = '2026-04-16';

$smsService = new HandoverSmsService();

echo "--- TESTING SMS TRIGGERS ---\n";

// 1. Test Shift Close SMS
$ledger = DailyCashLedger::where('user_id', $ownerId)->whereDate('ledger_date', $date)->first();
if ($ledger) {
    echo "Triggering Shift Close SMS...\n";
    try {
        $smsService->sendDailyMasterSheetClosedSms($ledger);
        echo "SUCCESS: Shift Close SMS triggered without crash.\n";
    } catch (\Exception $e) {
        echo "FAIL: Shift Close SMS failed: " . $e->getMessage() . "\n";
    }
}

// 2. Test Profit Submission SMS
$handover = FinancialHandover::where('user_id', $ownerId)
    ->whereDate('handover_date', $date)
    ->where('handover_type', 'accountant_to_owner')
    ->first();

if ($handover) {
    echo "Triggering Profit Submission SMS...\n";
    try {
        $smsService->sendProfitSubmissionToBossSms($handover);
        echo "SUCCESS: Profit Submission SMS triggered without crash.\n";
    } catch (\Exception $e) {
        echo "FAIL: Profit Submission SMS failed: " . $e->getMessage() . "\n";
    }
}
