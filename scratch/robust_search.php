<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;

echo "--- SEARCHING ALL PRODUCTS FOR 'CAM' ---\n";
$products = Product::where('name', 'like', '%Cam%')->get();
foreach($products as $p) {
    echo "Product ID: {$p->id} | Name: {$p->name}\n";
    foreach($p->variants as $v) {
        echo "  - Variant ID: {$v->id} | Name: {$v->name} | Measurement: {$v->measurement}\n";
    }
}

if ($products->isEmpty()) {
    echo "No matching products found. Listing top 50 products instead:\n";
    $all = Product::limit(50)->get();
    foreach($all as $p) echo "ID: {$p->id} | Name: {$p->name}\n";
}
