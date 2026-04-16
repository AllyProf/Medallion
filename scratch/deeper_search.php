<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

echo "--- SEARCHING FOR TEQUILA OR REAL ---\n";
$prods = Product::where('name', 'like', '%Tequila%')
    ->orWhere('name', 'like', '%Real%')
    ->orWhere('name', 'like', '%TOT%')
    ->with('variants')
    ->get();

foreach($prods as $p) {
    echo "Product ID: {$p->id} | Name: {$p->name}\n";
    foreach($p->variants as $v) {
        echo "  - Variant ID: {$v->id} | Name: {$v->name} | Unit: {$v->unit}\n";
    }
}
