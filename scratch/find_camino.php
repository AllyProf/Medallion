<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;

$variants = ProductVariant::whereHas('product', function($q){
    $q->where('name', 'like', '%Camino%');
})->with('product')->get();

echo "--- CAMINO VARIANTS ---\n";
foreach($variants as $v) {
    echo "Variant ID: {$v->id} | Product: {$v->product->name} | Variant: {$v->name} | Unit: {$v->unit} | Measure: {$v->measurement}\n";
    
    // Check counter stock
    $stock = StockLocation::where('product_variant_id', $v->id)
        ->where('location', 'counter')
        ->first();
    
    if ($stock) {
        echo "  - Counter Stock: {$stock->quantity} (ID: {$stock->id})\n";
    } else {
        echo "  - No Stock Record in Counter\n";
    }
}
