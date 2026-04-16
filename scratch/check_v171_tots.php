<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$v = ProductVariant::find(171);
if ($v) {
    echo "Total Tots: {$v->total_tots}\n";
    echo "Items Per Pkg: {$v->items_per_package}\n";
    echo "Can Sell in Tots: " . ($v->can_sell_in_tots ? 'YES' : 'NO') . "\n";
    echo "Measurement: {$v->measurement}\n";
} else {
    echo "Variant not found.\n";
}
