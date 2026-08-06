<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== SHIFT 48 FULL ORDER BREAKDOWN (To Find Source of 103,500) ===\n\n";

// Fetch all Shift 48 orders grouped by waiter
$orders48 = \App\Models\BarOrder::where('bar_shift_id', 48)
    ->whereIn('status', ['served', 'delivered'])
    ->orderBy('waiter_id')
    ->get();

$byWaiter = [];
foreach ($orders48 as $o) {
    $wid = $o->waiter_id;
    if (!isset($byWaiter[$wid])) $byWaiter[$wid] = ['orders' => [], 'total' => 0];
    $byWaiter[$wid]['orders'][] = $o;
    $byWaiter[$wid]['total'] += $o->total_amount;
}

$grandTotal = 0;
foreach ($byWaiter as $wid => $data) {
    $waiter = \App\Models\Staff::find($wid);
    $name = $waiter ? $waiter->full_name : "Unknown (ID: $wid)";
    echo "Waiter: $name (ID: $wid)\n";
    echo "  System Sales Total: TSh " . number_format($data['total']) . " from " . count($data['orders']) . " orders\n";
    
    // What did they submit in reconciliation?
    $rec = \App\Models\WaiterDailyReconciliation::where('bar_shift_id', 48)->where('waiter_id', $wid)->first();
    $submitted = $rec ? $rec->submitted_amount : 0;
    $expected = $rec ? $rec->expected_amount : 0;
    $diff = $submitted - $data['total'];
    echo "  Expected (in rec): TSh " . number_format($expected) . " | Submitted: TSh " . number_format($submitted) . " | Diff from raw sales: " . ($diff >= 0 ? '+' : '') . number_format($diff) . "\n";
    echo "\n";
    $grandTotal += $data['total'];
}

echo "Total raw sales on Shift 48: TSh " . number_format($grandTotal) . "\n\n";

echo "=== SHIFT 49 HANDOVER: How was the 167,000 composed? ===\n";
echo "Shift 49 Sales: TSh 63,500\n";
echo "Handover 63 (Glory->Naomy): TSh 167,000\n";
echo "Extra in handover: TSh " . number_format(167000 - 63500) . "\n\n";

echo "=== CHECK: Were Any Shift 48 Orders Paid Through Counter? ===\n";
// Any shift 48 order where paid_by_waiter_id = 53 (Glory)
$paidByGlory48 = \App\Models\BarOrder::where('bar_shift_id', 48)
    ->where('paid_by_waiter_id', 53)
    ->get();
echo "Shift 48 orders collected by Glory: " . $paidByGlory48->count() . " | Total: TSh " . number_format($paidByGlory48->sum('total_amount')) . "\n";
foreach ($paidByGlory48 as $o) {
    $waiter = \App\Models\Staff::find($o->waiter_id);
    echo "  Order #{$o->id} | Original Waiter: " . ($waiter ? $waiter->full_name : 'Unknown') . " | Total: TSh " . number_format($o->total_amount) . "\n";
}

echo "\n=== UNATTRIBUTED ORDERS: Shift 48 orders with no waiter_id ===\n";
$noWaiter = \App\Models\BarOrder::where('bar_shift_id', 48)->whereNull('waiter_id')->get();
echo "Count: " . $noWaiter->count() . "\n";

echo "\n=== RECONCILIATION vs RAW SALES DISCREPANCY SUMMARY ===\n";
$recs48 = \App\Models\WaiterDailyReconciliation::where('bar_shift_id', 48)->get();
$totalExpRec = $recs48->sum('expected_amount');
$totalSubRec = $recs48->sum('submitted_amount');
echo "Shift 48 Recs - Expected: TSh " . number_format($totalExpRec) . " | Submitted: TSh " . number_format($totalSubRec) . "\n";
echo "Shift 48 Raw Sales Total: TSh " . number_format($grandTotal) . "\n";
echo "Gap (Recs Expected vs Raw Sales): TSh " . number_format($totalExpRec - $grandTotal) . "\n";
echo "Shift 48 Handover (H66): TSh 324,000\n";
echo "Shift 48 Recs Submitted vs Handover 66: Gap = TSh " . number_format(324000 - $totalSubRec) . "\n";
echo "\nSo the 103,500 in Glory's handover (H63) = Extra physical cash she had beyond Shift 49 sales.\n";
echo "Breakdown: 63,500 (Shift 49 sales) + 103,500 (held from Shift 48 transactions) = 167,000 TSh handed over.\n";

