<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;

$affected = Product::where('user_id', $ownerId)
    ->where('category', 'Lager Beer (bottles)')
    ->update(['category' => 'Lager Beer (Bottles)']);

echo "Updated $affected products to fix the 'bottles' capitalization.\n";
