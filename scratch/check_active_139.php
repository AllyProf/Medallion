<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$v = ProductVariant::find(139);
if ($v) {
    echo "Variant ID: 139\n";
    echo "Name: " . $v->name . "\n";
    echo "Product Name: " . $v->product->name . "\n";
    echo "Product Is Active: " . $v->product->is_active . "\n";
    echo "Product Category: " . $v->product->category . "\n";
} else {
    echo "Variant 139 not found.\n";
}
