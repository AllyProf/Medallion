<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockMovement;
use App\Models\ProductVariant;

$queries = [
    'M/Water Big' => '%Water Big%',
    'M/Water Small' => '%Water Small%',
    'Bavaria Chupa' => '%Bavaria Chupa%',
    'Red Bull' => '%Red Bull%'
];

echo "--- WAREHOUSE STOCK ANALYSIS (Deep Dive) ---\n";

foreach ($queries as $label => $pattern) {
    echo "\n>>> PRODUCT: $label <<<\n";
    $variant = ProductVariant::where('name', 'like', $pattern)->first();
    if (!$variant) {
        echo "Variant not found.\n";
        continue;
    }

    $movements = StockMovement::where('product_variant_id', $variant->id)
        ->where(function($q) {
            $q->where('to_location', 'warehouse')
              ->orWhere('from_location', 'warehouse');
        })
        ->orderBy('created_at', 'asc')
        ->get();

    $runningBalance = 0;
    foreach ($movements as $m) {
        $change = 0;
        if ($m->to_location === 'warehouse') {
            $change = $m->quantity;
            $type = "RECEIPT/IN";
        } elseif ($m->from_location === 'warehouse') {
            $change = -$m->quantity;
            $type = "TRANSFER/OUT";
        } else {
            continue; // Movement unrelated to warehouse
        }

        $runningBalance += $change;
        echo "Date: {$m->created_at} | Action: $type | Change: " . ($change >= 0 ? '+' : '') . $change . " | Balance: $runningBalance\n";
    }
    
    echo "FINAL WAREHOUSE BALANCE: $runningBalance\n";
}
