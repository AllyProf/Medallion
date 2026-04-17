<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\BarOrder;
use App\Models\OrderItem;

$shiftId = 2; // S000002
$orders = BarOrder::where('bar_shift_id', $shiftId)
    ->whereIn('status', ['served', 'delivered'])
    ->with('items.productVariant')
    ->get();

$totalRevenue = 0;
$totalCogs = 0;
$totalProfit = 0;

echo "# Detailed Proof for Shift S000002\n\n";
echo "| Order # | Item | Qty | Unit Price | Unit Cost | Row Revenue | Row Cost | Row Profit |\n";
echo "|---------|------|-----|------------|-----------|-------------|----------|------------|\n";

foreach ($orders as $order) {
    foreach ($order->items as $item) {
        $v = $item->productVariant;
        $revenue = $item->unit_price * $item->quantity;
        $cost = ($v->buying_price_per_unit ?? 0) * $item->quantity;
        $profit = $revenue - $cost;

        $totalRevenue += $revenue;
        $totalCogs += $cost;
        $totalProfit += $profit;

        echo "| {$order->order_number} | {$v->display_name} | {$item->quantity} | " . number_format($item->unit_price) . " | " . number_format($v->buying_price_per_unit ?? 0) . " | " . number_format($revenue) . " | " . number_format($cost) . " | " . number_format($profit) . " |\n";
    }
}

echo "\n## TOTAL SUMMARY\n";
echo "- **Total Revenue**: TSh " . number_format($totalRevenue) . "\n";
echo "- **Total COGS**: TSh " . number_format($totalCogs) . "\n";
echo "- **Total Shift Profit**: TSh " . number_format($totalProfit) . "\n";

if ($totalProfit == 133986) {
    echo "\n✅ **MATCH CONFIRMED**: The calculated profit exactly matches the TSh 133,986 shown in the reconciliation.";
} else {
    echo "\n❌ **DISCREPANCY**: The calculated profit is TSh " . number_format($totalProfit) . ".";
}
