<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\StockTransfer;

echo "--- M/Water Small Inspection ---\n";
$variant = ProductVariant::where('name', 'like', '%M/Water Small%')->first();
if (!$variant) {
    echo "Variant not found.\n";
    exit;
}

echo "Variant ID: {$variant->id}\n";
echo "Name: {$variant->name}\n";
echo "Items Per Package: {$variant->items_per_package}\n";

echo "\n--- Stock Locations ---\n";
$locations = StockLocation::where('product_variant_id', $variant->id)->get();
foreach ($locations as $loc) {
    echo "Location: {$loc->location} | Qty: {$loc->quantity}\n";
}

echo "\n--- Recent Transfers ---\n";
$transfers = StockTransfer::where('product_variant_id', $variant->id)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "\n--- Stock Movement History ---\n";
$movements = \App\Models\StockMovement::where('product_variant_id', $variant->id)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($movements as $m) {
    echo "Type: {$m->movement_type} | From: {$m->from_location} | To: {$m->to_location} | Qty: {$m->quantity} | Ref: {$m->reference_type} #{$m->reference_id} | Created: {$m->created_at}\n";
}

