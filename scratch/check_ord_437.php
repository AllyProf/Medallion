<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;

$orderNumber = 'ORD-437';
$o = BarOrder::where('order_number', $orderNumber)->with(['items', 'kitchenOrderItems'])->first();

echo "ORDER: $orderNumber\n";
echo "Status: " . $o->status . "\n";
echo "Payment Status: " . $o->payment_status . "\n";
echo "Drinks Count: " . $o->items->count() . "\n";
echo "Food Count: " . $o->kitchenOrderItems->count() . "\n";
foreach($o->kitchenOrderItems as $f) {
    echo "- Food: {$f->food_item_name} | Price: {$f->total_price}\n";
}
foreach($o->items as $i) {
    echo "- Drink: " . ($i->productVariant->product->name ?? 'N/A') . " | Price: {$i->total_price}\n";
}
