<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$v = \App\Models\ProductVariant::whereHas('product', function($q){
    $q->where('name', 'LIKE', '%Red Bull%');
})->first();

echo "VARIANT_DATA:" . json_encode($v) . "\n";
