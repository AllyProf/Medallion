<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

// 1. Find the correct variant by name to be safe (since IDs might differ)
$variant = ProductVariant::where('name', 'Camino Tot')->first();

if (!$variant) {
    die("Error: 'Camino Tot' variant not found in the live database.\n");
}

$userId = 4; // This is the owner ID detected on your system
$qty = 0.62; // Set to 0.62 to ensure it displays as exactly 11 shots (due to DB precision)

DB::beginTransaction();
try {
    // 2. Update counter stock
    StockLocation::updateOrCreate(
        ['product_variant_id' => $variant->id, 'location' => 'counter'],
        ['user_id' => $userId, 'quantity' => $qty]
    );

    // 3. Log the history
    StockMovement::create([
        'user_id' => $userId,
        'product_variant_id' => $variant->id,
        'from_location' => 'supplier',
        'to_location' => 'counter',
        'quantity' => $qty,
        'movement_type' => 'adjustment',
        'notes' => 'Manual adjustment: set to exactly 11 shots.'
    ]);

    DB::commit();
    echo "SUCCESS: Camino Tot stock is now set to 11.00 on your live site!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
