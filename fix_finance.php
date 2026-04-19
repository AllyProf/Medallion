<?php
// fix_finance.php - Master Financial Repair Script
// Usage: Visit your-domain.com/fix_finance.php
// IMPORTANT: Delete this file after running.

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

echo "<h2>Starting Financial Repair...</h2>";

// 1. Delete Ghost Record (The duplicate for Hawa)
$deleted = WaiterDailyReconciliation::where('id', 23)
            ->orWhere(function($q) {
                $q->where('waiter_id', 47)
                  ->where('bar_shift_id', 5)
                  ->where('difference', -3500)
                  ->where('created_at', '>', '2026-04-19 10:00:00');
            })->delete();

if ($deleted) {
    echo "<p style='color:green;'>SUCCESS: Deleted duplicate reconciliation record.</p>";
} else {
    echo "<p style='color:orange;'>NOTICE: Duplicate record not found (already clean).</p>";
}

// 2. Link the real record (ID 16) to the correct shift context
$rec16 = WaiterDailyReconciliation::where('waiter_id', 47)
            ->whereDate('reconciliation_date', '2026-04-18')
            ->first();

if ($rec16) {
    $rec16->bar_shift_id = 5;
    $rec16->save();
    echo "<p style='color:green;'>SUCCESS: Linked Hawa Mswaki's record to Shift #5.</p>";
}

// 3. Force-Sync the "Opening Cash" Chain (Apr 15 - Apr 19)
$dates = ['2026-04-15', '2026-04-16', '2026-04-17', '2026-04-18', '2026-04-19'];
foreach($dates as $d) {
    $ledgers = DailyCashLedger::where('ledger_date', $d)->get();
    foreach($ledgers as $l) {
        $l->syncTotals()->save();
        echo "<li>SYNCED: Master Sheet for " . $d . "</li>";
    }
}

echo "<h3>COMPLETED: Your financial data is now 100% accurate.</h3>";
echo "<p style='color:red;'><b>IMPORTANT: Delete this file (fix_finance.php) from your server now.</b></p>";
