<?php
/**
 * Stock Data Correction Script
 * Fixes any StockLocation quantities that don't match their receipt history.
 * Run: php storage/fix_stock_data.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Stock Data Correction Script ===\n\n";

// Find all stock_location records and compare with receipt sums
$locations = \App\Models\StockLocation::where('location', 'warehouse')
    ->where('quantity', '>', 0)
    ->get();

$corrected = 0;
$noIssue = 0;

foreach ($locations as $loc) {
    $receiptSum = \App\Models\StockReceipt::where('product_variant_id', $loc->product_variant_id)
        ->where('user_id', $loc->user_id)
        ->sum('total_units');

    // Also subtract any completed transfers (stock moved out)
    $transferOut = \App\Models\StockTransfer::where('product_variant_id', $loc->product_variant_id)
        ->where('user_id', $loc->user_id)
        ->whereIn('status', ['completed'])
        ->sum('total_units');

    $expected = $receiptSum - $transferOut;
    $actual = (float) $loc->quantity;
    $diff = round(abs($actual - $expected), 4);

    if ($diff > 0.001) {
        $variant = \App\Models\ProductVariant::with('product')->find($loc->product_variant_id);
        $name = optional($variant->product)->name . ' - ' . optional($variant)->name;
        echo "FIXING: {$name}\n";
        echo "  Actual: {$actual} | Expected: {$expected} | Diff: {$diff}\n";
        $loc->update(['quantity' => $expected]);
        echo "  ✓ Corrected to {$expected}\n\n";
        $corrected++;
    } else {
        $noIssue++;
    }
}

echo "=== Done ===\n";
echo "Corrected: {$corrected} items\n";
echo "No issue: {$noIssue} items\n";
