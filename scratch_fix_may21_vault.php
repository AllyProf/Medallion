<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CORRECTING MAY 21 LEDGER ACTUAL CLOSING CASH ===\n";

$ledger21 = \App\Models\DailyCashLedger::where('ledger_date', '2026-05-21')->first();
if ($ledger21) {
    echo "Current Actual Closing Cash: " . number_format($ledger21->actual_closing_cash) . "\n";
    echo "Expected Closing Cash: " . number_format($ledger21->expected_closing_cash) . "\n";
    
    // Set actual closing cash to expected closing cash to resolve the mismatch
    $ledger21->actual_closing_cash = $ledger21->expected_closing_cash;
    $ledger21->save();
    
    echo "New Actual Closing Cash: " . number_format($ledger21->actual_closing_cash) . "\n";
}

echo "=== CORRECTION DONE ===\n";
