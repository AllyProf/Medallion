<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;

$ownerId = 4;
$date = '2026-04-16';

$ledger = DailyCashLedger::where('user_id', $ownerId)
    ->whereDate('ledger_date', $date)
    ->first();

if ($ledger) {
    echo "Date: {$ledger->ledger_date}\n";
    echo "Status: {$ledger->status}\n";
    echo "Profit Generated: {$ledger->profit_generated}\n";
    echo "Cash Received: {$ledger->total_cash_received}\n";
    echo "Total Expenses: {$ledger->total_expenses}\n";
    echo "Carried Forward: {$ledger->carried_forward}\n";
} else {
    echo "No ledger found for $date\n";
}
