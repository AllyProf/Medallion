<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;
$brands = Product::where('user_id', $ownerId)
    ->where('is_active', true)
    ->pluck('brand')
    ->map(fn($b) => strtoupper(trim($b)))
    ->unique()
    ->filter()
    ->sort()
    ->values()
    ->all();

echo "DISTRIBUTOR GROUPS (BRANDS):\n";
print_r($brands);
