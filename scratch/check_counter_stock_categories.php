<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;

$ownerId = 4;

$counterStock = StockLocation::where('user_id', $ownerId)
    ->where('location', 'counter')
    ->where('quantity', '>', 0)
    ->with('productVariant.product')
    ->get();

$categoriesAtCounter = [];
foreach ($counterStock as $stock) {
    if ($stock->productVariant && $stock->productVariant->product) {
        $cat = $stock->productVariant->product->category;
        if (!isset($categoriesAtCounter[$cat])) {
            $categoriesAtCounter[$cat] = 0;
        }
        $categoriesAtCounter[$cat]++;
    }
}

echo "CATEGORIES CURRENTLY IN COUNTER STOCK (>0 QUANTITY):\n";
print_r($categoriesAtCounter);

// Also check open bottles just in case
$openBottles = \App\Models\OpenBottle::where('user_id', $ownerId)
    ->where('tots_remaining', '>', 0)
    ->with('productVariant.product')
    ->get();

$openCats = [];
foreach ($openBottles as $ob) {
    if ($ob->productVariant && $ob->productVariant->product) {
        $cat = $ob->productVariant->product->category;
        if (!isset($openCats[$cat])) {
            $openCats[$cat] = 0;
        }
        $openCats[$cat]++;
    }
}

echo "\nCATEGORIES WITH OPEN BOTTLES:\n";
print_r($openCats);
