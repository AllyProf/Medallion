<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\StockMovement;

echo "--- VERIFYING STOCK ADJUSTMENT ---\n";
$stock = StockLocation::where('product_variant_id', 171)
    ->where('location', 'counter')
    ->first();

if ($stock && $stock->quantity == 11) {
    echo "VERIFIED: StockLocation quantity is 11.00\n";
} else {
    echo "FAILED: StockLocation quantity is " . ($stock->quantity ?? 'NOT FOUND') . "\n";
}

$move = StockMovement::where('product_variant_id', 171)
    ->where('movement_type', 'adjustment')
    ->latest()
    ->first();

if ($move && $move->quantity == 11) {
    echo "VERIFIED: StockMovement log found for 11.00\n";
    echo "Notes: {$move->notes}\n";
} else {
    echo "FAILED: StockMovement log not found or incorrect.\n";
}
