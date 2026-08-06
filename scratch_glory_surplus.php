<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== RECONCILIATION #156 DETAILED ANALYSIS ===\n";
echo "This is GLORY GERALD (ID: 53, Counter Staff) on Shift #49\n";
echo "Expected: 63,500 | Submitted: 167,000 | Difference: +103,500\n\n";

// ---- What the system counted as expected ----
echo "=== SHIFT 49 ORDERS: Sales that form the 63,500 expected ===\n";
$orders49 = \App\Models\BarOrder::where('bar_shift_id', 49)->whereIn('status', ['served','delivered'])->get();
$total = 0;
foreach ($orders49 as $o) {
    $total += $o->total_amount;
    echo "Order #{$o->id} | Total: " . number_format($o->total_amount) . " | Status: {$o->status} | PayStatus: {$o->payment_status}\n";
}
echo "Total sales on Shift 49: " . number_format($total) . "\n\n";

// ---- What was submitted by Glory ----
echo "=== WHAT GLORY SUBMITTED IN THE HANDOVER ===\n";
$h63 = \App\Models\FinancialHandover::find(63);
echo "Handover #63: TSh " . number_format($h63->amount) . " (Submitted by Glory, Shift 49)\n";
echo "Breakdown: " . json_encode($h63->payment_breakdown) . "\n\n";

// ---- Check if reconciliation notes explain the surplus ----
echo "=== RECONCILIATION NOTES FOR #156 ===\n";
$rec156 = \App\Models\WaiterDailyReconciliation::find(156);
echo "Notes raw: " . $rec156->notes . "\n\n";
$notes = json_decode($rec156->notes, true);
echo "Submitted breakdown: " . json_encode($notes['submitted_breakdown'] ?? []) . "\n";
echo "Recorded breakdown: " . json_encode($notes['recorded_breakdown'] ?? []) . "\n";
echo "Waiter note: " . ($notes['waiter_note'] ?? 'none') . "\n\n";

// ---- Check ALL handovers linked to Shift 49 ----
echo "=== ALL HANDOVERS FOR SHIFT 49 ===\n";
$handovers49 = \App\Models\FinancialHandover::where('bar_shift_id', 49)->get();
foreach ($handovers49 as $h) {
    echo "ID: {$h->id} | Amount: " . number_format($h->amount) . " | Status: {$h->status} | By: {$h->accountant_id} | Notes: {$h->notes}\n";
}

// ---- Check paid amounts on all Shift 48 orders to see if Glory was collecting from Shift 48 too ----
echo "\n=== ORDERS PAID BY GLORY (ID 53) ACROSS ALL SHIFTS ON MAY 21 ===\n";
$byGlory = \App\Models\BarOrder::where('paid_by_waiter_id', 53)
    ->whereDate('created_at', '2026-05-21')
    ->get();
$sumGlory = $byGlory->sum('total_amount');
echo "Total paid collections by Glory: " . number_format($sumGlory) . "\n";
foreach ($byGlory as $o) {
    echo "  Order #{$o->id} | Shift: {$o->bar_shift_id} | Total: " . number_format($o->total_amount) . " | PayStatus: {$o->payment_status}\n";
}

// ---- Check Shift 48 orders (not Shift 49) paid through Glory's counter ----
echo "\n=== SHIFT 48 ORDERS WHERE WAITER COLLECTED CASH: Sum ===\n";
$rec48 = \App\Models\WaiterDailyReconciliation::where('bar_shift_id', 48)->get();
$totalExpected48 = $rec48->sum('expected_amount');
$totalSubmitted48 = $rec48->sum('submitted_amount');
echo "Total Expected from Shift 48 waiters: " . number_format($totalExpected48) . "\n";
echo "Total Submitted from Shift 48 waiters: " . number_format($totalSubmitted48) . "\n";
echo "Their total sum matches the 324,000 handover? Handover was: 324,000\n\n";

// ---- Check if the 167,000 could be: Shift 49 sales (63,500) + some Shift 48 waiter money held by counter ----
echo "=== THEORY CHECK: Did Glory hold Shift 48 waiter money? ===\n";
$theoryExtra = 167000 - 63500;
echo "Surplus above Shift 49 sales: " . number_format($theoryExtra) . "\n";
echo "Shift 48 total submitted: " . number_format($totalSubmitted48) . "\n";
echo "Shift 48 handover (H66): 324,000\n";
echo "Does 324,000 + 167,000 = 491,000 (the day's total handover)? " . (324000 + 167000 == 491000 ? "YES ✅" : "NO ❌") . "\n";
echo "Sum of Shift 48 recs: " . number_format($totalSubmitted48) . " vs handover: 324,000\n";
echo "Possible explanation: Glory may have collected extra payments not tied to Shift 49 orders.\n";

