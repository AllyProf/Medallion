<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\OrderItem;
use App\Models\StockLocation;

$variantIds = [26, 48, 27, 28]; // Soda, Heineken, Water Big, Water Small

echo "--- Counter Stock Audit ---\n";
foreach ($variantIds as $id) {
    $sl = StockLocation::where('product_variant_id', $id)->where('location', 'counter')->first();
    $qty = $sl ? $sl->quantity : 0;
    
    // Check sales for this variant today (excluding cancelled)
    $sales = OrderItem::where('product_variant_id', $id)
        ->whereHas('order', function($q) {
            $q->where('status', '!=', 'cancelled')
              ->whereDate('created_at', '2026-04-15');
        })
        ->sum('quantity');

    echo "Variant ID: $id | Current Stock: $qty | Sales Today: $sales\n";
}
