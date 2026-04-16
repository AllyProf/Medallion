<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\ProductVariant;
use App\Models\OpenBottle;

$ownerId = 1; // Assuming owner ID is 1

echo "--- COUNTER STOCK (All Items) ---\n";
$stock = StockLocation::where('user_id', $ownerId)
    ->where('location', 'counter')
    ->with('productVariant.product')
    ->get();

foreach ($stock as $s) {
    if (!$s->productVariant) {
        echo "Missing Variant for Stock ID {$s->id}\n";
        continue;
    }
    $variant = $s->productVariant;
    $product = $variant->product;
    $openPortions = OpenBottle::where('product_variant_id', $variant->id)->sum('tots_remaining');
    
    echo "ID: {$variant->id} | Name: {$product->name} " . ($variant->display_name ? "({$variant->display_name})" : "({$variant->name})") . " | Category: {$product->category} | Qty: {$s->quantity} | Open: {$openPortions}\n";
}

echo "\n--- ITEMS WITH ZERO STOCK AT COUNTER ---\n";
// Let's see if there are spirits with 0 stock
$spirits = ProductVariant::whereHas('product', function($q) use ($ownerId) {
        $q->where('user_id', $ownerId)
          ->where(function($sq) {
              $sq->where('category', 'like', '%spirit%')
                ->orWhere('category', 'like', '%whisky%')
                ->orWhere('category', 'like', '%cognac%')
                ->orWhere('category', 'like', '%gin%')
                ->orWhere('category', 'like', '%vodka%')
                ->orWhere('category', 'like', '%alcoholic%');
          });
    })
    ->with(['product', 'stockLocations' => function($q) use ($ownerId) {
        $q->where('user_id', $ownerId)->where('location', 'counter');
    }])
    ->get();

foreach ($spirits as $v) {
    $cs = $v->stockLocations->where('location', 'counter')->first();
    $qty = $cs ? $cs->quantity : 0;
    echo "Spirit: {$v->product->name} ({$v->name}) | Qty at Counter: {$qty}\n";
}
