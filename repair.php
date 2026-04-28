<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;
echo "Repairing...\n";
foreach(WaiterDailyReconciliation::all() as $r){
    $r->difference = (float)$r->submitted_amount - (float)$r->expected_amount;
    $r->save();
}
foreach(DailyCashLedger::orderBy('ledger_date', 'asc')->get() as $l){
    $l->syncTotals()->save();
}
echo "Repair Done\n";
