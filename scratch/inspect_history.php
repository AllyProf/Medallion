<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockReceipt;
use App\Models\StockMovement;

$variantId = 28;

echo "--- Stock History for Variant $variantId ---\n";
$movements = StockMovement::where('product_variant_id', $variantId)
    ->orderBy('created_at', 'asc')
    ->get();

$runningBalance = 0;
foreach ($movements as $m) {
    if ($m->to_location === 'warehouse') {
        $runningBalance += $m->quantity;
    } elseif ($m->from_location === 'warehouse') {
        $runningBalance -= $m->quantity;
    }
    echo "Date: {$m->created_at} | Type: {$m->movement_type} | Change: " . ($m->to_location === 'warehouse' ? '+' : '-') . "{$m->quantity} | Running Balance: $runningBalance\n";
}
