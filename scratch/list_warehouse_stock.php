<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StockLocation;
use App\Models\ProductVariant;

$rows = StockLocation::with(['productVariant.product'])
    ->where('location', 'warehouse')
    ->where('user_id', 4)
    ->orderBy('id')
    ->get();

echo "Warehouse items: {$rows->count()}\n\n";
foreach ($rows as $r) {
    $v = $r->productVariant;
    $name = $v ? ($v->name . ' | ' . ($v->product->name ?? '')) : 'NO VARIANT';
    echo "stock#{$r->id} variant#{$r->product_variant_id} qty={$r->quantity} :: {$name}\n";
}
