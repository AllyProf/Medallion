<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = \App\Models\StockTransfer::where('transfer_number', 'ST2026040002')->first();
if ($t && $t->productVariant) {
    $pv = $t->productVariant;
    echo "Variant: {$pv->name}\n";
    echo "Buying Price: {$pv->buying_price_per_unit}\n";
    echo "Selling Price (Bottle): {$pv->selling_price_per_unit}\n";
    echo "Total Glasses/Tots: {$pv->total_tots}\n";
    echo "Selling Price (Glass): {$pv->selling_price_per_tot}\n";
}
