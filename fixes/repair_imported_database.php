<?php
// fixes/repair_imported_database.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;
use Illuminate\Support\Facades\DB;

echo "=== Repairing Imported Database ===\n\n";

// 1. Fix the 'difference' column for ALL reconciliation records
// This ensures that (Submitted - Expected) is what determines the shortage,
// removing any stale or manual data entry errors from the import.
echo "Step 1: Recalculating all shortages...\n";
$recs = WaiterDailyReconciliation::all();
$fixedRecs = 0;
foreach ($recs as $r) {
    $realDiff = (float)$r->submitted_amount - (float)$r->expected_amount;
    if (abs((float)$r->difference - $realDiff) > 0.01) {
        $r->difference = $realDiff;
        $r->save();
        $fixedRecs++;
    }
}
echo ">> Recalculated difference for $fixedRecs records.\n\n";

// 2. Sync all Daily Master Sheets (Ledgers)
// This recalculates profit, carried forward, and opening cash chains.
echo "Step 2: Syncing Daily Master Sheets...\n";
$ledgers = DailyCashLedger::orderBy('ledger_date', 'asc')->get();
foreach ($ledgers as $l) {
    echo "  Syncing {$l->ledger_date}... ";
    $l->syncTotals()->save();
    echo "Done\n";
}
echo ">> All ledgers synced.\n\n";

echo "=== REPAIR COMPLETE ===\n";
