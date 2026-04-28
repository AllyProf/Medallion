<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

$rec = WaiterDailyReconciliation::find(4);
if (!$rec) { echo "Already gone.\n"; exit(0); }

echo "Deleting: ID={$rec->id} Date={$rec->reconciliation_date} Diff={$rec->difference}\n";
$rec->delete();
echo "Deleted.\n";

$ledger = DailyCashLedger::where('ledger_date', '2026-04-15')->first();
if ($ledger) { $ledger->syncTotals()->save(); echo "Ledger Apr 15 re-synced.\n"; }
echo "Done.\n";
