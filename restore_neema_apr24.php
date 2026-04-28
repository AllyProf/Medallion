<?php
// restore_neema_apr24.php - Restore Neema's real shortage for Apr 24
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;
use App\Models\Staff;

$neema = Staff::where('full_name', 'like', '%Neema%')->first();
if (!$neema) { echo "Neema not found.\n"; exit(1); }
echo "Neema ID: {$neema->id}\n";

// Find her Apr 24 record
$rec = WaiterDailyReconciliation::where('waiter_id', $neema->id)
    ->whereDate('reconciliation_date', '2026-04-24')
    ->first();

if (!$rec) { echo "No Apr 24 record found.\n"; exit(1); }
echo "Found: ID={$rec->id} | Exp={$rec->expected_amount} | Sub={$rec->submitted_amount} | Diff={$rec->difference} | Status={$rec->status}\n";

// Restore the real shortage: set submitted to expected - 4500
$rec->submitted_amount = $rec->expected_amount - 4500;
$rec->difference = -4500;
$rec->status = 'submitted';
$rec->save();
echo "Restored: Sub={$rec->submitted_amount} | Diff={$rec->difference} | Status={$rec->status}\n";

// Re-sync the Apr 24 ledger
$ledger = DailyCashLedger::where('ledger_date', '2026-04-24')->first();
if ($ledger) {
    $ledger->syncTotals()->save();
    echo "Ledger Apr 24 re-synced. New totalDayShortage={$ledger->totalDayShortage}\n";
}

echo "Done.\n";
