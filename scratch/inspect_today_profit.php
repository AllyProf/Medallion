<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\ProductVariant;

$ownerId = 4;
$date = '2026-04-16'; // Date from user screenshot

$orders = BarOrder::where('user_id', $ownerId)
    ->whereDate('created_at', $date)
    ->where('status', 'served')
    ->with('items.productVariant')
    ->get();

echo "--- INSPECTING PROFIT FOR $date ---\n";
echo "Total Served Orders: " . $orders->count() . "\n";

$totalProfit = 0;
foreach ($orders as $order) {
    echo "Order ID: {$order->id} | Total: {$order->total_amount}\n";
    foreach ($order->items as $item) {
        $variant = $item->productVariant;
        $buyingPrice = $variant->buying_price_per_unit ?? 0;
        $qty = $item->quantity;
        
        if (($item->sell_type ?? 'unit') === 'tot') {
            $totsPerBtl = $variant->total_tots ?: 1;
            $qty = $item->quantity / $totsPerBtl;
        }
        
        $itemProfit = $item->total_price - ($qty * $buyingPrice);
        $totalProfit += $itemProfit;
        
        echo "  - Item: {$item->productVariant->name} | Qty: $qty | Price: {$item->total_price} | Buy Price: $buyingPrice | Profit: $itemProfit\n";
    }
}

echo "Total Calculated Profit: $totalProfit\n";
