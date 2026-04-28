<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

// Step 1: Fix all reconciliation difference values
echo "Step 1: Recalculating reconciliation differences...\n";
$fixed = 0;
foreach (WaiterDailyReconciliation::all() as $r) {
    $real = (float)$r->submitted_amount - (float)$r->expected_amount;
    if (abs((float)$r->difference - $real) > 0.01) {
        $r->difference = $real;
        $r->save();
        $fixed++;
        echo "  Fixed ID={$r->id} | was {$r->difference}, now {$real}\n";
    }
}
echo ">> Fixed $fixed records.\n\n";

// Step 2: Sync all ledgers in date order
echo "Step 2: Syncing all Daily Master Sheets...\n";
foreach (DailyCashLedger::orderBy('ledger_date', 'asc')->get() as $l) {
    echo "  Syncing {$l->ledger_date}... ";
    $l->syncTotals()->save();
    echo "Done\n";
}
echo ">> All Ledgers Synced.\n\n";
echo "=== REPAIR COMPLETE ===\n";
