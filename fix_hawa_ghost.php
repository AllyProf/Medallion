<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;

echo "--- Medallion Specific Debt Cleanup --- \n";

// Hawa only has 3500 shortage. 
// It currently shows on Apr 18 and Apr 19.
// Since the orders are shift 5 (the 18th's shift), the 19th one is the duplicate.

$duplicate = WaiterDailyReconciliation::where('waiter_id', 47)
    ->whereDate('reconciliation_date', '2026-04-19')
    ->where('difference', -3500)
    ->first();

if ($duplicate) {
    echo "Found ghost debt for Hawa on Apr 19. Deleting...\n";
    $duplicate->delete();
    echo ">>> DELETED.\n";
} else {
    echo "Apr 19 ghost debt not found.\n";
}

echo "\n--- Cleanup Complete ---\n";
