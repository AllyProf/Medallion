<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;
$products = Product::where('user_id', $ownerId)->get(['name', 'brand', 'category']);

echo "CURRENT CATEGORIES & BRANDS FOR USER 4:\n";
foreach ($products as $p) {
    if (in_array($p->name, ['Ceres Juice', 'M/Water Big', 'M/Water Small', 'Bonite Soda', 'Castle Lite', 'Heineken lager', 'Safari Ndogo'])) {
        echo sprintf("[Cat: %-25s] [Brand: %-25s] %s\n", $p->category, $p->brand, $p->name);
    }
}
