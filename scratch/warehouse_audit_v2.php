<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockMovement;
use App\Models\StockLocation;
use App\Models\ProductVariant;

$queries = [
    'M/Water Big' => '%Water Big%',
    'M/Water Small' => '%Water Small%',
    'Bavaria Chupa' => '%Bavaria Chupa%',
    'Red Bull' => '%Red Bull%'
];

echo str_pad("Found Name", 25) . " | " . str_pad("Receipts", 10) . " | " . str_pad("Transfers", 10) . " | " . str_pad("Warehouse", 10) . " | Diff\n";
echo str_repeat("-", 75) . "\n";

foreach ($queries as $label => $pattern) {
    $variant = ProductVariant::where('name', 'like', $pattern)->first();
    if (!$variant) {
        echo str_pad("NOT FOUND: $label", 25) . "\n";
        continue;
    }

    $receipts = StockMovement::where('product_variant_id', $variant->id)
        ->where('to_location', 'warehouse')
        ->where('movement_type', 'receipt')
        ->sum('quantity');

    $transfers = StockMovement::where('product_variant_id', $variant->id)
        ->where('from_location', 'warehouse')
        ->where('movement_type', 'transfer')
        ->sum('quantity');
    
    $sl = StockLocation::where('product_variant_id', $variant->id)
        ->where('location', 'warehouse')
        ->first();
    $actual = $sl ? $sl->quantity : 0;
    
    $diff = $receipts - $transfers - $actual;

    echo str_pad($variant->name, 25) . " | " . 
         str_pad(number_format($receipts, 0), 10) . " | " . 
         str_pad(number_format($transfers, 0), 10) . " | " . 
         str_pad(number_format($actual, 0), 10) . " | " . 
         number_format($diff, 2) . "\n";
}
