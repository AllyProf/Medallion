<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$brands = \App\Models\Product::select('brand')->get()->pluck('brand')->unique();
foreach ($brands as $b) {
    echo "Brand: [" . $b . "]\n";
}
