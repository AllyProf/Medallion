<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;

$queries = ['%Pure%', '%Heaven%', '%Bonne%', '%Smirnoff%', '%Vodka%', '%Pure Heaven%', '%Smirnoff%'];

foreach ($queries as $q) {
    echo "--- Product Search: $q ---\n";
    $products = Product::where('name', 'like', $q)->get();
    foreach ($products as $p) {
        echo "Product ID: {$p->id} | Name: {$p->name}\n";
        foreach ($p->variants as $v) {
            echo "  - Variant ID: {$v->id} | Name: {$v->name} | Measurement: {$v->measurement}\n";
        }
    }
}
