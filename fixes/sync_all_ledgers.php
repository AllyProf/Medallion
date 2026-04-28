<?php
// fixes/sync_all_ledgers.php - Full Financial Chain Repair
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;

echo "=== Financial Chain Repair: Syncing All Ledgers ===\n\n";

$ledgers = DailyCashLedger::orderBy('ledger_date', 'asc')->get();

foreach ($ledgers as $l) {
    echo "Syncing {$l->ledger_date}... ";
    $l->syncTotals()->save();
    echo "Done. (Carried Forward: " . number_format($l->carried_forward) . ")\n";
}

echo "\n=== Done! All ledgers synced and roll-overs corrected. ===\n";
