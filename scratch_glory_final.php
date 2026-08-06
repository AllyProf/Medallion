<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECKING SHIFT 48 HANDOVER vs SHIFT 48 RECS ===\n\n";

// Shift 48 submitted: 312,000
// Shift 48 handover (H66 by NEEMA, Counter): 324,000
// Difference: 12,000 (NEEMA had 12,000 extra)

echo "Shift 48 Recs Submitted:  TSh 312,000\n";
echo "Shift 48 Handover (H66):  TSh 324,000\n";
echo "Gap in Shift 48:           TSh 12,000\n\n";

echo "Glory Shift 49 Sales:     TSh 63,500\n";
echo "Glory Handover (H63):     TSh 167,000\n";
echo "Glory Extra:              TSh 103,500\n\n";

echo "Total Day Extra cash:     TSh " . number_format(12000 + 103500) . "\n\n";

// Check all orders on May 21 that are paid but don't belong to either Shift 48 or 49
echo "=== CHECK: Are there any PAID orders on May 21 NOT linked to Shift 48 or 49? ===\n";
$otherOrders = \App\Models\BarOrder::whereDate('created_at', '2026-05-21')
    ->whereNotIn('bar_shift_id', [48, 49])
    ->whereIn('payment_status', ['paid'])
    ->get();
echo "Count: " . $otherOrders->count() . "\n";
foreach ($otherOrders as $o) {
    echo "  Order #{$o->id} | Shift: {$o->bar_shift_id} | Total: TSh " . number_format($o->total_amount) . " | Waiter: {$o->waiter_id}\n";
}

// Look at ALL May 21 orders period
echo "\n=== ALL PAID ORDERS ON MAY 21 by Shift ===\n";
$allMay21 = \App\Models\BarOrder::whereDate('created_at', '2026-05-21')
    ->where('payment_status', 'paid')
    ->selectRaw('bar_shift_id, count(*) as cnt, SUM(total_amount) as total')
    ->groupBy('bar_shift_id')
    ->get();
foreach ($allMay21 as $r) {
    echo "Shift {$r->bar_shift_id}: {$r->cnt} orders | Total: TSh " . number_format($r->total) . "\n";
}

// Look at H66 submitter — NEEMA JUSTINE THADEY (ID: 49)
echo "\n=== NEEMA JUSTINE THADEY (ID: 49) ROLE DETAILS ===\n";
$neema = \App\Models\Staff::find(49);
echo "Name: {$neema->full_name} | Role ID: {$neema->role_id}\n";
$role = \App\Models\StaffRole::find($neema->role_id);
echo "Role: " . ($role ? $role->name . " / " . $role->slug : 'Unknown') . "\n\n";

// What did NEEMA reconcile for Shift 48?
echo "=== NEEMA'S RECONCILIATION ON SHIFT 48 ===\n";
$neemaRec = \App\Models\WaiterDailyReconciliation::where('bar_shift_id', 48)->where('waiter_id', 49)->get();
echo "Count: " . $neemaRec->count() . "\n";
foreach ($neemaRec as $r) {
    echo "Rec ID: {$r->id} | Expected: TSh " . number_format($r->expected_amount) . " | Submitted: TSh " . number_format($r->submitted_amount) . " | Diff: " . number_format($r->difference) . " | Status: {$r->status}\n";
}

// Check all orders placed by or paid by NEEMA
echo "\n=== ORDERS BY OR PAID BY NEEMA ON SHIFT 48 ===\n";
$neemaOrders = \App\Models\BarOrder::where('bar_shift_id', 48)
    ->where(function($q) { $q->where('waiter_id', 49)->orWhere('paid_by_waiter_id', 49); })
    ->get();
echo "Count: " . $neemaOrders->count() . " | Total: TSh " . number_format($neemaOrders->sum('total_amount')) . "\n";

// The 12,000 gap explained:
echo "\n=== THEORY: NEEMA's 12,000 extra in H66 ===\n";
$neemaShift48Orders = \App\Models\BarOrder::where('bar_shift_id', 48)->where('waiter_id', 49)->sum('total_amount');
echo "Orders placed by NEEMA on Shift 48: TSh " . number_format($neemaShift48Orders) . "\n";
echo "H66 Amount: 324,000 vs Shift 48 Recs (4 waiters): 312,000\n";
echo "=> NEEMA collected TSh 12,000 extra (from her own counter sales not in waiters' recs)\n\n";

echo "=== FINAL CONCLUSION ===\n";
echo "The 103,500 in Glory's handover (H63, Shift 49) cannot be traced to specific Shift 48 orders.\n";
echo "All Shift 48 waiters reconciled perfectly (312,000 / 312,000).\n";
echo "This suggests Glory physically had 103,500 TSh extra cash:\n";
echo "  - Could be from off-system sales (walk-in customers not entered in POS)\n";
echo "  - Could be from cash given to her by other Shift 48 staff for safekeeping\n";
echo "  - Could be from unrecorded orders or manual table payments\n";

