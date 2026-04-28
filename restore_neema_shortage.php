<?php
// restore_neema_shortage.php
// Restore NEEMA JUSTINE THADEY's real shortage of -4,500 for Apr 24
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

// ID 44 = Neema's Apr 24 record (waiter_id=49)
$rec = WaiterDailyReconciliation::find(44);
if (!$rec) { echo "Record not found.\n"; exit(1); }

echo "Before: ID={$rec->id} Exp={$rec->expected_amount} Sub={$rec->submitted_amount} Diff={$rec->difference} Status={$rec->status}\n";

// Restore the real shortage: submitted should be 4,500 less than expected
$rec->submitted_amount = $rec->expected_amount - 4500; // 29000 - 4500 = 24500
$rec->difference = -4500;
$rec->status = 'submitted';
$rec->save();

echo "After:  ID={$rec->id} Exp={$rec->expected_amount} Sub={$rec->submitted_amount} Diff={$rec->difference} Status={$rec->status}\n";

// Re-sync the Apr 24 ledger
$ledger = DailyCashLedger::where('ledger_date', '2026-04-24')->first();
if ($ledger) {
    $ledger->syncTotals()->save();
    echo "Ledger Apr 24 re-synced. totalDayShortage={$ledger->totalDayShortage}\n";
}

echo "Done. Apr 24 MISSING should now show 12,000 (Miriam 7,500 + Neema 4,500).\n";
