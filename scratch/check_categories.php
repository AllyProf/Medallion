<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;
$cats = Product::where('user_id', $ownerId)
    ->pluck('category')
    ->unique()
    ->filter()
    ->values()
    ->toArray();

echo "CATEGORIES IN DATABASE:\n";
print_r($cats);
