<?php
use App\Models\FinancialHandover;
use App\Models\DailyCashLedger;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Remove the older duplicate recovery pay
$h6 = FinancialHandover::find(6);
if ($h6) {
    echo "Deleting ghost ID 6...\n";
    $h6->delete();
}

// Move the freshly paid debt to target date
$h7 = FinancialHandover::find(7);
if ($h7) {
    echo "Moving user-submitted debt ID 7 to April 17...\n";
    $h7->handover_date = '2026-04-17';
    $h7->save();
}

// Clear all ledgers for a fresh sync
DailyCashLedger::all()->each(function($l) {
    $l->total_cash_received = 0;
    $l->total_digital_received = 0;
    $l->save();
});

echo "Ready to sync!\n";
