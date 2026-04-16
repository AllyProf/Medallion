<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

$variantId = 171;
$userId = 4; // Detected earlier
$qty = 11.00;

DB::beginTransaction();
try {
    // 1. Update or Create StockLocation
    $stock = StockLocation::updateOrCreate(
        ['product_variant_id' => $variantId, 'location' => 'counter'],
        ['user_id' => $userId, 'quantity' => $qty]
    );

    // 2. Log StockMovement
    StockMovement::create([
        'user_id' => $userId,
        'product_variant_id' => $variantId,
        'from_location' => 'supplier',
        'to_location' => 'counter',
        'quantity' => $qty,
        'movement_type' => 'adjustment',
        'notes' => 'Manual add 11 tot as requested by user.'
    ]);

    DB::commit();
    echo "SUCCESS: Added 11 units of Variant $variantId to counter stock.\n";
    echo "StockLocation ID: {$stock->id}\n";
    echo "New Quantity: {$stock->quantity}\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
