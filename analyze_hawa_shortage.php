<?php

use App\Models\Staff;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;

$hawa = Staff::where('full_name', 'like', '%HAWA MSWAKI%')->first();
$date = '2026-04-19';

$rec = WaiterDailyReconciliation::where('waiter_id', $hawa->id)
    ->whereDate('reconciliation_date', $date)
    ->first();

if (!$rec) {
    echo "No reconciliation record found.\n";
    exit;
}

echo "--- RECONCILIATION DETAILS ---\n";
echo "ID: " . $rec->id . "\n";
echo "Expected Total: " . number_format($rec->expected_amount) . "\n";
echo "Actual Total Handed Over: " . number_format($rec->actual_amount) . "\n";
echo "Shortage: " . number_format($rec->difference) . "\n";

$orders = BarOrder::where('reconciliation_id', $rec->id)->get();
echo "\n--- ORDERS LINKED TO THIS RECONCILIATION (" . $orders->count() . ") ---\n";
$sumCash = 0;
$sumDigital = 0;
foreach ($orders as $o) {
    echo "  #{$o->order_number} | {$o->payment_method} | TSh " . number_format($o->total_amount) . "\n";
    if ($o->payment_method === 'cash') $sumCash += $o->total_amount;
    else $sumDigital += $o->total_amount;
}

echo "\nCalculated Totals from these orders:\n";
echo "  Cash Part: " . number_format($sumCash) . "\n";
echo "  Digital Part: " . number_format($sumDigital) . "\n";
echo "  Total: " . number_format($sumCash + $sumDigital) . "\n";

// Now look for unlinked orders for Hawa on that day
$unlinked = BarOrder::where('waiter_id', $hawa->id)
    ->whereDate('created_at', $date)
    ->whereNull('reconciliation_id')
    ->whereIn('status', ['served', 'delivered'])
    ->get();

if ($unlinked->count() > 0) {
    echo "\n--- WARNING: UNRECONCILED ORDERS FOR HAWA ON THIS DAY (" . $unlinked->count() . ") ---\n";
    $sumUnlinked = 0;
    foreach ($unlinked as $u) {
        echo "  #{$u->order_number} | {$u->payment_method} | TSh " . number_format($u->total_amount) . "\n";
        $sumUnlinked += $u->total_amount;
    }
    echo "  TOTAL UNRECONCILED: " . number_format($sumUnlinked) . "\n";
}
