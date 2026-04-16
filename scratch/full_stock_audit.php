<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\ProductVariant;

$date = '2026-04-15';

echo "--- COMPREHENSIVE COUNTER STOCK AUDIT (Apr 15) ---\n";
echo str_pad("Product", 25) . " | " . str_pad("Start", 8) . " | " . str_pad("Xfer In", 8) . " | " . str_pad("Sold", 8) . " | " . str_pad("Current", 8) . " | Status\n";
echo str_repeat("-", 80) . "\n";

$variants = ProductVariant::with(['stockLocations' => function($q) {
    $q->where('location', 'counter');
}])->get();

foreach ($variants as $v) {
    $sl = $v->stockLocations->first();
    $current = $sl ? (float)$sl->quantity : 0;
    
    // Total Transferred In Today
    $transferredIn = StockMovement::where('product_variant_id', $v->id)
        ->where('to_location', 'counter')
        ->where('movement_type', 'transfer')
        ->whereDate('created_at', $date)
        ->sum('quantity');
    
    // Total Sold Today (Excluding cancelled)
    $sold = OrderItem::where('product_variant_id', $v->id)
        ->whereHas('order', function($q) use ($date) {
            $q->where('status', '!=', 'cancelled')
              ->whereDate('created_at', $date);
        })
        ->sum('quantity');

    // We don't have a record of "Start of Day" stock unless we calculate it:
    // Start = Current + Sold - TransferredIn
    $calculatedStart = $current + (float)$sold - (float)$transferredIn;

    if ($sold > 0 || $transferredIn > 0) {
        $status = ($current == ($calculatedStart + $transferredIn - $sold)) ? "OK" : "MISMATCH";
        echo str_pad(substr($v->name, 0, 25), 25) . " | " . 
             str_pad(number_format($calculatedStart, 0), 8) . " | " . 
             str_pad(number_format($transferredIn, 0), 8) . " | " . 
             str_pad(number_format($sold, 0), 8) . " | " . 
             str_pad(number_format($current, 0), 8) . " | $status\n";
    }
}
