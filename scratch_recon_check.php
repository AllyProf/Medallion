<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== WAITER RECONCILIATION RECORDS FOR MAY 21 ===\n\n";

$recs = \App\Models\WaiterDailyReconciliation::with('waiter')
    ->whereDate('reconciliation_date', '2026-05-21')
    ->get();

foreach ($recs as $r) {
    echo "ID: " . $r->id . " | Waiter: " . ($r->waiter->full_name ?? 'Unknown') . "\n";
    echo "  Shift ID: " . $r->bar_shift_id . "\n";
    echo "  Expected: " . number_format($r->expected_amount) . "\n";
    echo "  Recorded: " . number_format($r->recorded_amount ?? 0) . "\n";
    echo "  Submitted: " . number_format($r->submitted_amount) . "\n";
    echo "  Difference: " . number_format($r->difference) . "\n";
    echo "  Status: " . $r->status . "\n";
    echo "  Notes: " . $r->notes . "\n";
    echo "---\n";
}

echo "\n=== CHECKING ORDERS FOR LOVENESS & GIFT ON MAY 21 SHIFT ===\n\n";

// Get shift IDs for May 21
$shiftIds = \App\Models\BarShift::whereDate('opened_at', '2026-05-21')->pluck('id')->toArray();
echo "Shifts on May 21: " . implode(', ', $shiftIds) . "\n\n";

// Loveness orders
$loveness = \App\Models\Staff::where('full_name', 'LIKE', '%LOVENESS%')->first();
$gift = \App\Models\Staff::where('full_name', 'LIKE', '%GIFT%')->first();

if ($loveness) {
    $loveOrders = \App\Models\BarOrder::whereIn('bar_shift_id', $shiftIds)
        ->where('waiter_id', $loveness->id)
        ->whereIn('status', ['served', 'delivered'])
        ->get(['id', 'total_amount', 'payment_method', 'status']);
    $loveTotal = $loveOrders->sum('total_amount');
    echo "LOVENESS (" . $loveness->full_name . ") - Total from orders: TSh " . number_format($loveTotal) . "\n";
    foreach ($loveOrders as $o) {
        echo "  Order #" . $o->id . ": TSh " . number_format($o->total_amount) . " | " . $o->payment_method . "\n";
    }
}

echo "\n";

if ($gift) {
    $giftOrders = \App\Models\BarOrder::whereIn('bar_shift_id', $shiftIds)
        ->where('waiter_id', $gift->id)
        ->whereIn('status', ['served', 'delivered'])
        ->get(['id', 'total_amount', 'payment_method', 'status']);
    $giftTotal = $giftOrders->sum('total_amount');
    echo "GIFT (" . $gift->full_name . ") - Total from orders: TSh " . number_format($giftTotal) . "\n";
    foreach ($giftOrders as $o) {
        echo "  Order #" . $o->id . ": TSh " . number_format($o->total_amount) . " | " . $o->payment_method . "\n";
    }
}
