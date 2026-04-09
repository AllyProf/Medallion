<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$v = \App\Models\ProductVariant::whereHas('product', function($q) {
    $q->where('category', 'like', '%spirit%')->orWhere('category', 'like', '%drink%');
})->where('unit', '!=', 'ml')->first();

if ($v) {
    echo "Variant: " . $v->name . "\n";
    echo "Unit: " . $v->unit . "\n";
} else {
    echo "No non-ml spirit/drink found\n";
}
