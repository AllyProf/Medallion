<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$o113 = \App\Models\BarOrder::where('order_number', 'ORD-113')->first();
$o166 = \App\Models\BarOrder::where('order_number', 'ORD-166')->first();

echo "ORD_113:" . json_encode($o113) . "\n";
echo "ORD_166:" . json_encode($o166) . "\n";
