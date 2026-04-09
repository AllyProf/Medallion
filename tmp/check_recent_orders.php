<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orders = \App\Models\BarOrder::latest()->take(3)->with('items.productVariant')->get();
foreach($orders as $o) {
    echo "Order: {$o->order_number}, Date: {$o->created_at}\n";
    foreach($o->items as $i) {
        echo " - Item: {$i->productVariant->name}, SellType: {$i->sell_type}, Qty: {$i->quantity}, Total: {$i->total_price}\n";
    }
}
