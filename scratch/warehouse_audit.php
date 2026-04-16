<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\ProductVariant;

$samples = [
    'M/Water Big' => ['unit' => 'btl'],
    'M/Water Small' => ['unit' => 'btl'],
    'Bavaria Chupa' => ['unit' => 'btl'],
    'Red Bull' => ['unit' => 'can']
];

echo "--- WAREHOUSE INTEGRITY AUDIT ---\n";
echo str_pad("Product", 20) . " | " . str_pad("Receipts", 10) . " | " . str_pad("Transferred", 11) . " | " . str_pad("Expected", 10) . " | " . str_pad("Actual", 10) . " | Status\n";
echo str_repeat("-", 85) . "\n";

foreach ($samples as $name => $info) {
    $variant = ProductVariant::where('name', $name)->first();
    if (!$variant) {
        echo str_pad($name, 20) . " | Variant Not Found\n";
        continue;
    }

    // Receipts to Warehouse
    $receipts = StockMovement::where('product_variant_id', $variant->id)
        ->where('to_location', 'warehouse')
        ->where('movement_type', 'receipt')
        ->sum('quantity');

    // Transfers out of Warehouse
    $transfers = StockMovement::where('product_variant_id', $variant->id)
        ->where('from_location', 'warehouse')
        ->where('movement_type', 'transfer')
        ->sum('quantity');
    
    // Manual adjustments or other movements?
    $adjustments = StockMovement::where('product_variant_id', $variant->id)
        ->where('to_location', 'warehouse')
        ->whereNotIn('movement_type', ['receipt', 'transfer'])
        ->sum('quantity') - 
        StockMovement::where('product_variant_id', $variant->id)
        ->where('from_location', 'warehouse')
        ->whereNotIn('movement_type', ['receipt', 'transfer'])
        ->sum('quantity');

    $expected = $receipts - $transfers + $adjustments;
    
    $sl = StockLocation::where('product_variant_id', $variant->id)
        ->where('location', 'warehouse')
        ->first();
    $actual = $sl ? $sl->quantity : 0;

    $status = (abs($expected - $actual) < 0.01) ? "OK" : "MISMATCH";
    
    echo str_pad($name, 20) . " | " . 
         str_pad(number_format($receipts, 0), 10) . " | " . 
         str_pad(number_format($transfers, 0), 11) . " | " . 
         str_pad(number_format($expected, 0), 10) . " | " . 
         str_pad(number_format($actual, 0), 10) . " | $status\n";
}
