<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = \App\Models\Product::find(1);
if ($p) {
    echo "Product ID 1: " . $p->name . "\n";
    foreach ($p->variants as $v) {
        echo "  - Variant ID {$v->id}: {$v->name} (Buying: {$v->buying_price_per_unit}, Selling: {$v->selling_price_per_unit})\n";
    }
} else {
    echo "Product ID 1 not found\n";
}
