<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;
$products = Product::where('user_id', $ownerId)
    ->get(['name', 'category']);

echo "PRODUCT NAMES PER CATEGORY:\n";
foreach ($products as $p) {
    echo "[" . $p->category . "] - " . $p->name . "\n";
}
