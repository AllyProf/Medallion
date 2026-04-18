<?php

use App\Models\DailyCashLedger;
use App\Models\BarShift;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ownerId = 2; // Accountant's owner ID
$dateStr = '2026-04-17';

echo "--- LEDGERS ---\n";
$ledgers = DailyCashLedger::where('user_id', $ownerId)->where('ledger_date', '>=', '2026-04-15')->orderBy('ledger_date', 'asc')->get();
foreach ($ledgers as $l) {
    echo "Date: {$l->ledger_date->format('Y-m-d')}, ID: {$l->id}, Status: {$l->status}, Opening: {$l->opening_cash}, Received: {$l->total_cash_received}, Profit: {$l->profit_generated}, Carried: {$l->carried_forward}\n";
}

echo "\n--- SHIFTS for $dateStr ---\n";
$shifts = BarShift::where('user_id', $ownerId)->whereDate('opened_at', $dateStr)->get();
foreach ($shifts as $s) {
    echo "Shift ID: {$s->id}, Status: {$s->status}, Opened: {$s->opened_at}\n";
}

echo "\n--- ORDERS for $dateStr ---\n";
$shiftIds = $shifts->pluck('id');
$orderTotals = BarOrder::whereIn('bar_shift_id', $shiftIds)->whereIn('status', ['served', 'delivered'])->sum('total_amount');
echo "Total Served/Delivered Order Value: " . number_format($orderTotals) . "\n";

echo "\n--- WAITER RECONCILIATIONS for $dateStr ---\n";
$recs = WaiterDailyReconciliation::whereIn('bar_shift_id', $shiftIds)->get();
foreach ($recs as $r) {
    echo "Waiter ID: {$r->waiter_id}, Status: {$r->status}, Expected: {$r->expected_amount}, Recorded: " . ($r->cash_collected + $r->mobile_money_collected) . "\n";
}
