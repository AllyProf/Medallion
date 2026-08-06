<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;

foreach (['M/Water Small', 'Jameson 1L'] as $name) {
    $v = ProductVariant::where('name', $name)->first();
    $wh = StockLocation::where('product_variant_id', $v->id)->where('location', 'warehouse')->where('user_id', 4)->first();
    echo "{$name}: packaging={$v->packaging} items_per_package={$v->items_per_package} unit={$v->unit} wh_qty={$wh->quantity}\n";
}
