<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

echo "--- AUTO-COMPLETING PENDING TRANSFERS ---\n";

$pendingTransfers = StockTransfer::whereIn('status', ['approved', 'prepared'])
    ->with('productVariant')
    ->get();

if ($pendingTransfers->isEmpty()) {
    echo "No pending approved/prepared transfers found.\n";
    exit;
}

foreach ($pendingTransfers as $item) {
    echo "Completing Transfer #{$item->transfer_number} for {$item->productVariant->product->name} (Qty: {$item->total_units})\n";
    
    DB::beginTransaction();
    try {
        $ownerId = $item->user_id;
        
        $warehouseStock = StockLocation::where('user_id', $ownerId)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('location', 'warehouse')
            ->first();

        if (!$warehouseStock || $warehouseStock->quantity < $item->total_units) {
            echo "Skipping: Insufficient warehouse stock for {$item->productVariant->product->name}.\n";
            DB::rollBack();
            continue;
        }

        // Get or create counter stock location
        $counterStock = StockLocation::firstOrCreate(
            [
                'user_id' => $ownerId,
                'product_variant_id' => $item->product_variant_id,
                'location' => 'counter',
            ],
            [
                'quantity' => 0,
                'average_buying_price' => $warehouseStock->average_buying_price,
                'selling_price' => $warehouseStock->selling_price,
                'selling_price_per_tot' => $warehouseStock->selling_price_per_tot,
            ]
        );

        // Deduct from warehouse
        $warehouseStock->decrement('quantity', $item->total_units);

        // Weighted Average Costing for Counter
        $existingCounterQty = $counterStock->quantity;
        $currentCounterAve = $counterStock->average_buying_price;
        $incomingQty = $item->total_units;
        $warehouseAve = $warehouseStock->average_buying_price;

        $newCounterAve = ($existingCounterQty + $incomingQty) > 0 
            ? (($existingCounterQty * $currentCounterAve) + ($incomingQty * $warehouseAve)) / ($existingCounterQty + $incomingQty)
            : $warehouseAve;

        // Add to counter and update prices
        $counterStock->update([
            'quantity' => $existingCounterQty + $incomingQty,
            'average_buying_price' => $newCounterAve,
            'selling_price' => $warehouseStock->selling_price,
            'selling_price_per_tot' => $warehouseStock->selling_price_per_tot,
        ]);

        // Update transfer status
        $item->update(['status' => 'completed']);

        // Record stock movement
        StockMovement::create([
            'user_id' => $ownerId,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => 'transfer',
            'from_location' => 'warehouse',
            'to_location' => 'counter',
            'quantity' => $item->total_units,
            'unit_price' => $warehouseStock->average_buying_price,
            'reference_type' => StockTransfer::class,
            'reference_id' => $item->id,
            'created_by' => $ownerId,
            'notes' => 'Stock moved from warehouse to counter (Auto-fix)',
        ]);

        DB::commit();
        echo "Successfully completed Transfer #{$item->id}.\n";
    } catch (\Exception $e) {
        DB::rollBack();
        echo "Error: {$e->getMessage()}\n";
    }
}

echo "\n--- DONE ---\n";
