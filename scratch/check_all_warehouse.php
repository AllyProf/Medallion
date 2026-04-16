<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\ProductVariant;

$variants = ProductVariant::whereIn('name', [
    'M/Water Big', 
    'M/Water Small', 
    'Ceres Juice', 
    'Bavaria Chupa', 
    'Baltika'
])->get();

echo "--- Warehouse Stock Levels ---\n";
foreach ($variants as $v) {
    $sl = StockLocation::where('product_variant_id', $v->id)
        ->where('location', 'warehouse')
        ->first();
    
    $qty = $sl ? $sl->quantity : 0;
    $ipp = $v->items_per_package ?: 1;
    $pkgs = floor($qty / $ipp);
    $loose = $qty % $ipp;

    echo "Variant: {$v->name} | Total Qty: $qty | Items/Pkg: $ipp | pkgs: $pkgs | loose: $loose\n";
}
