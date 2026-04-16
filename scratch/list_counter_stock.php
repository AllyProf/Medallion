<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;

echo "--- LISTING ALL COUNTER STOCK ---\n";
$stock = StockLocation::where('location', 'counter')
    ->with('productVariant.product')
    ->get();

foreach($stock as $s) {
    $pName = $s->productVariant->product->name ?? 'N/A';
    $vName = $s->productVariant->name ?? 'N/A';
    echo "ID: {$s->id} | Product: $pName | Variant: $vName | Qty: {$s->quantity} | PID: {$s->product_variant_id}\n";
}

if ($stock->isEmpty()) {
    echo "Counter stock is completely empty in DB.\n";
}
