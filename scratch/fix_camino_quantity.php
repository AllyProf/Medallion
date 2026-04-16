<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;

$stock = StockLocation::where('product_variant_id', 171)
    ->where('location', 'counter')
    ->first();

if ($stock) {
    $stock->update(['quantity' => 11 / 18]);
    echo "SUCCESS: Quantity updated to 11/18 (" . (11/18) . ")\n";
} else {
    echo "ERROR: Stock record not found.\n";
}
