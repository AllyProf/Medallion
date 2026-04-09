<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\BarOrder;
use App\Models\KitchenOrderItem;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ownerId = 1; // Assuming owner ID 1 based on context
$mary = Staff::where('name', 'like', '%MARY%')->first();

if (!$mary) {
    echo "Waitress MARY not found.\n";
    exit;
}

echo "--- MARY'S FOOD ORDERS (MONTHLY) ---\n";
$orders = BarOrder::where('waiter_id', $mary->id)
    ->whereMonth('created_at', now()->month)
    ->with('kitchenOrderItems')
    ->get();

$totalFood = 0;
foreach ($orders as $order) {
    if ($order->kitchenOrderItems->count() > 0) {
        echo "Order #{$order->order_number} ({$order->created_at}): Status: {$order->payment_status}\n";
        foreach ($order->kitchenOrderItems as $item) {
            echo "  - Item: {$item->food_item_name} x{$item->quantity} = TSh " . number_format($item->total_price) . "\n";
            $totalFood += $item->total_price;
        }
    }
}
echo "Total Calculated Food Revenue for MARY: TSh " . number_format($totalFood) . "\n\n";

echo "--- TOTAL KITCHEN REVENUE CATEGORIES ---\n";
$kitchenSales = KitchenOrderItem::whereHas('order', function($q) use ($ownerId) {
    $q->where('user_id', $ownerId)->whereMonth('created_at', now()->month);
})
->select('food_item_name', DB::raw('SUM(total_price) as total'))
->groupBy('food_item_name')
->get();

foreach($kitchenSales as $sale) {
    echo "Product: {$sale->food_item_name} | Total: TSh " . number_format($sale->total) . "\n";
}

$handovers = \App\Models\FinancialHandover::where('department', 'food')
    ->whereMonth('handover_date', now()->month)
    ->where('handover_type', 'accountant_to_owner')
    ->where('status', 'confirmed')
    ->get();

echo "\n--- MANUAL KITCHEN HANDOVERS (MASTER SHEET) ---\n";
foreach($handovers as $h) {
    echo "Date: {$h->handover_date} | Amount: TSh " . number_format($h->amount) . " | Notes: {$h->notes}\n";
}
