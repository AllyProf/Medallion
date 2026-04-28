<?php
// clear_neema_justine_apr15.php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

// ID=4 is NEEMA JUSTINE's Apr 15 shortage of -1000
$rec = WaiterDailyReconciliation::find(4);
if (!$rec) { echo "Record not found.\n"; exit(1); }

echo "Before: ID={$rec->id} Exp={$rec->expected_amount} Sub={$rec->submitted_amount} Diff={$rec->difference} Status={$rec->status}\n";

// Clear the shortage - mark as settled
$rec->submitted_amount = $rec->expected_amount; // 15000
$rec->difference = 0;
$rec->status = 'reconciled';
$rec->save();

echo "After:  ID={$rec->id} Exp={$rec->expected_amount} Sub={$rec->submitted_amount} Diff={$rec->difference} Status={$rec->status}\n";

// Re-sync the Apr 15 ledger
$ledger = DailyCashLedger::where('ledger_date', '2026-04-15')->first();
if ($ledger) {
    $ledger->syncTotals()->save();
    echo "Ledger Apr 15 re-synced.\n";
}

echo "Done. NEEMA JUSTINE's Apr 15 shortage is now cleared.\n";
