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
    echo "StockRecordFound: YES\n";
    echo "ID: {$stock->id}\n";
    echo "Quantity: {$stock->quantity}\n";
    echo "User ID: {$stock->user_id}\n";
} else {
    echo "StockRecordFound: NO\n";
    // Check which user_id to use by looking at other counter stock
    $other = StockLocation::where('location', 'counter')->first();
    echo "Suggested User ID: " . ($other->user_id ?? '1') . "\n";
}
