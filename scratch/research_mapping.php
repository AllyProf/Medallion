<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;
$products = Product::where('user_id', $ownerId)->get(['name', 'brand', 'category']);

echo "PRODUCT CATEGORY VS BRAND MAPPING:\n";
foreach ($products as $p) {
    echo "ID: " . $p->id . " | Cat: [" . $p->category . "] | Brand: [" . $p->brand . "] | Name: " . $p->name . "\n";
}
