<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\StockMovement;

$orderNumbers = ['ORD-04', 'ORD-05', 'ORD-06'];
$orders = BarOrder::whereIn('order_number', $orderNumbers)->with('items.productVariant.product')->get();

echo "--- Order Status Check ---\n";
foreach ($orders as $order) {
    echo "ID: {$order->id} | Number: {$order->order_number} | Status: {$order->status} | Served At: {$order->served_at}\n";
    foreach ($order->items as $item) {
        $variant = $item->productVariant;
        echo "  - Item ID: {$item->id} | Variant ID: {$item->product_variant_id} | Product: " . ($variant->product->name ?? 'N/A') . " | Variant: {$variant->name} | Qty: {$item->quantity}\n";
        
        $movements = StockMovement::where('reference_type', BarOrder::class)
            ->where('reference_id', $order->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->get();
        
        if ($movements->isEmpty()) {
            echo "    -> No Stock Movements found for this item.\n";
        } else {
            foreach ($movements as $m) {
                echo "    -> Found Stock Movement: Type: {$m->movement_type} | Qty: {$m->quantity} | Created: {$m->created_at}\n";
            }
        }
    }
}
