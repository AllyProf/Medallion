<?php
/**
 * FIX: Finalize Stock Transfers ST2026040006, ST2026040007, ST2026040008
 * This script ensures warehouse stock doesn't go negative during transfer.
 * 
 * Usage: php fix_transfers.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

echo "Starting Stock Transfer Finalization...\n";

$transferNumbers = ['ST2026040006', 'ST2026040007', 'ST2026040008'];

$transfers = StockTransfer::whereIn('transfer_number', $transferNumbers)
    ->whereIn('status', ['approved', 'prepared', 'pending'])
    ->get();

if ($transfers->isEmpty()) {
    die("No pending/approved transfers found for these numbers.\n");
}

echo "Found " . $transfers->count() . " items to process.\n";

DB::beginTransaction();
try {
    foreach ($transfers as $item) {
        $ownerId = $item->user_id;
        $totalUnits = $item->total_units;
        $vName = $item->productVariant->name ?? "Variant ID: " . $item->product_variant_id;
        
        $warehouseStock = StockLocation::where('user_id', $ownerId)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('location', 'warehouse')
            ->first();

        if (!$warehouseStock) {
            $warehouseStock = StockLocation::create([
                'user_id' => $ownerId,
                'product_variant_id' => $item->product_variant_id,
                'location' => 'warehouse',
                'quantity' => 0,
                'average_buying_price' => $item->productVariant->buying_price_per_unit ?? 0,
                'selling_price' => $item->productVariant->selling_price_per_unit ?? 0,
            ]);
        }

        if ($warehouseStock->quantity < $totalUnits) {
            $diff = $totalUnits - $warehouseStock->quantity;
            echo " - Topping up WH for {$vName} by {$diff} units.\n";
            StockMovement::create([
                'user_id' => $ownerId,
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => 'adjustment',
                'to_location' => 'warehouse',
                'quantity' => $diff,
                'unit_price' => $warehouseStock->average_buying_price,
                'created_by' => $ownerId,
                'notes' => 'Auto-correction to prevent negative stock during transfer.',
            ]);
            $warehouseStock->increment('quantity', $diff);
        }

        $counterStock = StockLocation::firstOrCreate(
            ['user_id' => $ownerId, 'product_variant_id' => $item->product_variant_id, 'location' => 'counter'],
            [
                'quantity' => 0,
                'average_buying_price' => $warehouseStock->average_buying_price,
                'selling_price' => $warehouseStock->selling_price,
                'selling_price_per_tot' => $warehouseStock->selling_price_per_tot,
            ]
        );

        $warehouseStock->decrement('quantity', $totalUnits);
        
        $oldQty = $counterStock->quantity;
        $oldAve = $counterStock->average_buying_price;
        $inQty = $totalUnits;
        $inAve = $warehouseStock->average_buying_price;

        $newAve = ($oldQty + $inQty) > 0 
            ? (($oldQty * $oldAve) + ($inQty * $inAve)) / ($oldQty + $inQty)
            : $inAve;

        $counterStock->update([
            'quantity' => $oldQty + $inQty,
            'average_buying_price' => $newAve,
            'selling_price' => $warehouseStock->selling_price,
            'selling_price_per_tot' => $warehouseStock->selling_price_per_tot,
        ]);

        $item->update(['status' => 'completed']);

        StockMovement::create([
            'user_id' => $ownerId,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => 'transfer',
            'from_location' => 'warehouse',
            'to_location' => 'counter',
            'quantity' => $totalUnits,
            'unit_price' => $warehouseStock->average_buying_price,
            'reference_type' => 'App\Models\StockTransfer',
            'reference_id' => $item->id,
            'created_by' => $ownerId,
            'notes' => "Transferred from warehouse to counter stock (Scripted)",
        ]);

        echo " - SUCCESS: Finalized {$vName}\n";
    }

    DB::commit();
    echo "\nBatch finalization complete.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
