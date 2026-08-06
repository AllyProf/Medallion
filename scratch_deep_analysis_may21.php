<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DEEP ANALYSIS OF MAY 21 (SHIFTS 48 & 49) ===\n\n";

// 1. Fetch shifts
$shifts = \App\Models\BarShift::whereIn('id', [48, 49])->get();
foreach ($shifts as $s) {
    echo "Shift ID: {$s->id} | Opened: {$s->opened_at} | Closed: {$s->closed_at} | Status: {$s->status}\n";
}

echo "\n=== RECONCILIATIONS FOR SHIFTS ===\n";
$recons = \App\Models\WaiterDailyReconciliation::whereIn('bar_shift_id', [48, 49])->get();
foreach ($recons as $r) {
    $waiter = \App\Models\Staff::find($r->waiter_id);
    echo "Rec ID: {$r->id} | Waiter: " . ($waiter ? $waiter->full_name : 'Unknown') . " (ID: {$r->waiter_id}) | Shift: {$r->bar_shift_id} | Expected: " . number_format($r->expected_amount) . " | Submitted: " . number_format($r->submitted_amount) . " | Diff: " . number_format($r->difference) . " | Status: {$r->status}\n";
}

echo "\n=== ALL ORDERS PLACED ON SHIFT 49 ===\n";
$orders49 = \App\Models\BarOrder::where('bar_shift_id', 49)->get();
echo "Total orders count on Shift 49: " . $orders49->count() . "\n";
foreach ($orders49 as $o) {
    echo "Order #{$o->id} | Number: {$o->order_number} | Waiter ID: {$o->waiter_id} | Paid by Waiter ID: {$o->paid_by_waiter_id} | Total: " . number_format($o->total_amount) . " | Paid: " . number_format($o->paid_amount) . " | Status: {$o->status} | Pay Status: {$o->payment_status} | Method: {$o->payment_method}\n";
    // Check items
    $items = \App\Models\OrderItem::where('order_id', $o->id)->get();
    foreach ($items as $item) {
        $variant = \App\Models\ProductVariant::find($item->product_variant_id);
        $pName = $variant ? ($variant->product->name . ' ' . $variant->name) : 'Unknown Item';
        echo "  - {$item->quantity}x {$pName} @ TSh " . number_format($item->unit_price) . "\n";
    }
}

echo "\n=== ALL ORDERS PLACED ON SHIFT 48 ===\n";
$orders48 = \App\Models\BarOrder::where('bar_shift_id', 48)->get();
echo "Total orders count on Shift 48: " . $orders48->count() . "\n";

// Let's check payments recorded on Shift 49 and Shift 48
echo "\n=== ALL PAYMENTS RECORDED ON MAY 21 ===\n";
$payments = \App\Models\OrderPayment::whereDate('created_at', '2026-05-21')->get();
foreach ($payments as $p) {
    echo "Payment ID: {$p->id} | Order ID: {$p->order_id} | Amount: " . number_format($p->amount) . " | Method: {$p->payment_method} | Created At: {$p->created_at}\n";
}

echo "\n=== UNRECONCILED OR OUTSTANDING BILLS FOR GLORY GERALD (USER ID 53) ===\n";
// Let's check all orders where Glory Gerald was the waiter or served_by or paid_by
$gloryOrders = \App\Models\BarOrder::where(function($q) {
    $q->where('waiter_id', 53)
      ->orWhere('paid_by_waiter_id', 53);
})->whereDate('created_at', '2026-05-21')->get();
echo "Total orders involving Glory Gerald: " . $gloryOrders->count() . "\n";
foreach ($gloryOrders as $o) {
    echo "Order #{$o->id} | Shift: {$o->bar_shift_id} | Waiter: {$o->waiter_id} | Paid by Waiter: {$o->paid_by_waiter_id} | Total: " . number_format($o->total_amount) . " | Paid: " . number_format($o->paid_amount) . " | Status: {$o->status} | Pay Status: {$o->payment_status}\n";
}

