<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$products = Product::with('variants')->get();

foreach ($products as $p) {
    echo "Product: {$p->name}\n";
    foreach ($p->variants as $v) {
        echo "  - Variant ID: {$v->id} | Name: {$v->name} | Measurement: {$v->measurement} | Packaging: {$v->packaging}\n";
    }
}
