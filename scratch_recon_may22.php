<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DIAGNOSTIC FOR SHIFT #50 (MAY 22, 2026) ===\n\n";

$shift = \App\Models\BarShift::find(50);
if (!$shift) {
    echo "Shift #50 not found!\n";
    exit;
}

echo "Shift ID: " . $shift->id . "\n";
echo "Opened At: " . $shift->opened_at . "\n";
echo "Closed At: " . $shift->closed_at . "\n";
echo "Status: " . $shift->status . "\n\n";

// Get all orders under Shift #50
$orders = \App\Models\BarOrder::where('bar_shift_id', 50)
    ->whereIn('status', ['served', 'delivered'])
    ->get();

echo "Total orders count: " . $orders->count() . "\n";
echo "Total orders sum: TSh " . number_format($orders->sum('total_amount')) . "\n\n";

echo "=== ORDERS BREAKDOWN BY STAFF ===\n";

$staffGroups = $orders->groupBy(function($order) {
    if ($order->waiter_id) {
        $waiter = \App\Models\Staff::find($order->waiter_id);
        return $waiter ? $waiter->full_name . " (Waiter)" : "Unknown Waiter (ID: " . $order->waiter_id . ")";
    } elseif ($order->user_id) {
        $user = \App\Models\User::find($order->user_id);
        return $user ? ($user->staff->full_name ?? $user->name) . " (User/Counter)" : "Unknown User";
    }
    return "Unassigned";
});

foreach ($staffGroups as $staffName => $staffOrders) {
    echo "\nStaff: " . $staffName . "\n";
    echo "Total Expected: TSh " . number_format($staffOrders->sum('total_amount')) . "\n";
    foreach ($staffOrders as $o) {
        echo "  - Order #" . $o->id . " | Total: TSh " . number_format($o->total_amount) . " | Payment: " . $o->payment_method . " | Status: " . $o->status . "\n";
        // Fetch items
        $items = \App\Models\OrderItem::where('order_id', $o->id)->get();
        foreach ($items as $item) {
            $variant = \App\Models\ProductVariant::find($item->product_variant_id);
            $pName = $variant ? ($variant->product->name . ' ' . $variant->name) : 'Unknown Item';
            echo "    * " . $item->quantity . "x " . $pName . " @ TSh " . number_format($item->unit_price) . "\n";
        }
    }
}
