<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;

echo "--- PRODUCT 'IMAGE' CHECK ---\n";
// Search for variants matching 'image'
$variants = ProductVariant::where('name', 'like', '%image%')
    ->orWhereHas('product', function($q) {
        $q->where('name', 'like', '%image%');
    })
    ->with('product', 'stockLocations')
    ->get();

foreach ($variants as $v) {
    $counterStock = $v->stockLocations->where('location', 'counter')->first();
    echo "ID: {$v->id} | Name: {$v->name} | Prod: {$v->product->name} | Cat: {$v->product->category} | Qty: " . ($counterStock ? $counterStock->quantity : 'NONE') . "\n";
}

if ($variants->isEmpty()) {
    echo "No variants found matching 'image'.\n";
}
