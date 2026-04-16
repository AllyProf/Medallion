<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\FinancialHandover;
use App\Models\BarShift;
use App\Models\Staff;
use App\Models\BarOrder;

$ownerId = 4;
$date = '2026-04-16';

// SIMULATE CONTROLLER LOGIC
$bar_shift = BarShift::where('user_id', $ownerId)->where('status', 'open')->first();

$handoverQuery = FinancialHandover::where('user_id', $ownerId)
    ->where('handover_type', 'staff_to_accountant')
    ->whereDate('handover_date', $date);

$todayHandover = $handoverQuery->orderBy('created_at', 'desc')->first();

$targetShiftId = $todayHandover ? $todayHandover->bar_shift_id : ($bar_shift ? $bar_shift->id : null);

echo "--- SIMULATION FOR $date ---\n";
echo "Handover Found: " . ($todayHandover ? "YES (ID: {$todayHandover->id})" : "NO") . "\n";
echo "Target Shift ID: " . ($targetShiftId ?: "None") . "\n";

if ($targetShiftId) {
    $ordersCount = BarOrder::where('bar_shift_id', $targetShiftId)->where('status', 'served')->count();
    $ordersSum = BarOrder::where('bar_shift_id', $targetShiftId)->where('status', 'served')->sum('total_amount');
    echo "Orders in Target Shift: $ordersCount\n";
    echo "Total Value in Target Shift: $ordersSum\n";
    
    // Check Profit calculation roughly
    $orders = BarOrder::where('bar_shift_id', $targetShiftId)->where('status', 'served')->with('items.productVariant')->get();
    $totalProfit = 0;
    foreach ($orders as $order) {
        foreach ($order->items as $item) {
            $variant = $item->productVariant;
            $buyingPrice = $variant->buying_price_per_unit ?? 0;
            $qty = $item->quantity;
            $itemProfit = $item->total_price - ($qty * $buyingPrice);
            $totalProfit += $itemProfit;
        }
    }
    echo "Total Estimated Profit: $totalProfit\n";
}
