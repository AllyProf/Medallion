<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

echo "--- SEARCHING FOR 'TOT' OR 'CAMINO' ---\n";
// Case insensitive search
$variants = ProductVariant::where('name', 'like', '%TOT%')
    ->orWhere('name', 'like', '%Camino%')
    ->with('product')
    ->get();

foreach($variants as $v) {
    echo "Variant ID: {$v->id} | Name: {$v->name} | Product: " . ($v->product->name ?? 'N/A') . " | Unit: {$v->unit}\n";
}

if ($variants->isEmpty()) {
    echo "No variants found with 'TOT' or 'Camino' in the name.\n";
    
    echo "\n--- SEARCHING FOR TEQUILA ---\n";
    $variants2 = ProductVariant::where('name', 'like', '%Tequila%')
        ->orWhereHas('product', function($q){ $q->where('name', 'like', '%Tequila%'); })
        ->with('product')
        ->get();
        
    foreach($variants2 as $v) {
        echo "Variant ID: {$v->id} | Name: {$v->name} | Product: " . ($v->product->name ?? 'N/A') . "\n";
    }
}
