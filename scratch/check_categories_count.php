<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;
$categories = Product::where('user_id', $ownerId)->pluck('category')->toArray();

echo "UNIQUE CATEGORIES AND THEIR COUNTS:\n";
print_r(array_count_values($categories));

$variantsCategory = \App\Models\ProductVariant::whereHas('product', function($q) use ($ownerId) {
    $q->where('user_id', $ownerId);
})->with('product')->get()->pluck('product.category')->toArray();

echo "\nUNIQUE CATEGORIES FROM VARIANTS:\n";
print_r(array_count_values($variantsCategory));
