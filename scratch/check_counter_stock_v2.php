<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\User;

// Find the owner with the most counter stock
$owner = StockLocation::where('location', 'counter')
    ->select('user_id', \DB::raw('count(*) as total'))
    ->groupBy('user_id')
    ->orderBy('total', 'desc')
    ->first();

if (!$owner) {
    echo "No counter stock found in the entire database!\n";
    exit;
}

$ownerId = $owner->user_id;
echo "Assuming Owner ID: {$ownerId} (Total counter items: {$owner->total})\n\n";

echo "--- COUNTER STOCK ---\n";
$stock = StockLocation::where('user_id', $ownerId)
    ->where('location', 'counter')
    ->with('productVariant.product')
    ->get();

foreach ($stock as $s) {
    $variant = $s->productVariant;
    if (!$variant) continue;
    $product = $variant->product;
    echo "ID: {$variant->id} | Name: {$product->name} | Cat: {$product->category} | Qty: {$s->quantity}\n";
}

echo "\n--- SPIRITS (All) ---\n";
$spirits = \App\Models\ProductVariant::whereHas('product', function($q) use ($ownerId) {
        $q->where('user_id', $ownerId);
    })->with('product')->get();

foreach ($spirits as $v) {
    $c = strtolower($v->product->category);
    if (str_contains($c, 'spirit') || str_contains($c, 'whisky') || str_contains($c, 'cognac') || str_contains($c, 'gin') || str_contains($c, 'alcohol')) {
        $cs = StockLocation::where('user_id', $ownerId)->where('product_variant_id', $v->id)->where('location', 'counter')->first();
        echo "Spirit: {$v->product->name} | Cat: {$v->product->category} | Counter Qty: " . ($cs ? $cs->quantity : 'NONE') . "\n";
    }
}
