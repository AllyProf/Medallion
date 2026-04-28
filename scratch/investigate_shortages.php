<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

echo "=== STAFF CHECK ===\n";
$miriam = Staff::where('full_name', 'like', '%Miriam%')->first();
if ($miriam) {
    echo "Found Miriam: ID={$miriam->id}\n";
    $recs = WaiterDailyReconciliation::where('waiter_id', $miriam->id)->orderBy('reconciliation_date', 'desc')->get();
    foreach ($recs as $r) {
        echo "  REC ID: {$r->id} | Date: {$r->reconciliation_date} | Exp: {$r->expected_amount} | Sub: {$r->submitted_amount} | Diff: {$r->difference} | Status: {$r->status}\n";
    }
} else {
    echo "Miriam not found.\n";
}

$neema = Staff::where('full_name', 'like', '%Neema%')->first();
if ($neema) {
    echo "\nFound Neema: ID={$neema->id}\n";
    $recs = WaiterDailyReconciliation::where('waiter_id', $neema->id)->orderBy('reconciliation_date', 'desc')->get();
    foreach ($recs as $r) {
        echo "  REC ID: {$r->id} | Date: {$r->reconciliation_date} | Exp: {$r->expected_amount} | Sub: {$r->submitted_amount} | Diff: {$r->difference} | Status: {$r->status}\n";
    }
}

echo "\n=== LEDGER CHECK (2026-04-24) ===\n";
$ledger = DailyCashLedger::where('ledger_date', '2026-04-24')->first();
if ($ledger) {
    echo "Date: {$ledger->ledger_date}\n";
    echo "Opening: {$ledger->opening_cash}\n";
    echo "Cash Recv: {$ledger->total_cash_received}\n";
    echo "Digital Recv: {$ledger->total_digital_received}\n";
    echo "Expenses: {$ledger->total_expenses}\n";
    echo "Carried Fwd: {$ledger->carried_forward}\n";
    echo "Profit Gen: {$ledger->profit_generated}\n";
}
