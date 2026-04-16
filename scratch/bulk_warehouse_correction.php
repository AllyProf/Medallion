<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

// Mapped IDs and Target Quantities
$updates = [
    27 => 18, 28 => 24, 30 => 6, 24 => 48, 48 => 48, 52 => 12, 54 => 3, 55 => 4, 
    57 => 1, 58 => 2, 59 => 3, 61 => 2, 64 => 2, 65 => 2, 67 => 2, 69 => 1, 
    72 => 1, 78 => 2, 82 => 2, 83 => 2, 84 => 3, 85 => 2, 86 => 3, 87 => 1, 
    88 => 1, 89 => 3, 90 => 1, 91 => 1, 93 => 1, 95 => 1, 98 => 3, 148 => 1, 
    149 => 1, 151 => 4, 153 => 5, 155 => 1, 156 => 2, 158 => 2, 161 => 2, 164 => 3, 
    100 => 1, 103 => 1, 104 => 16, 106 => 12, 108 => 6, 109 => 11, 110 => 4, 111 => 14, 
    112 => 1, 113 => 1, 119 => 2, 121 => 4, 122 => 4, 123 => 2, 124 => 1, 125 => 2, 
    127 => 2, 129 => 2, 131 => 2, 116 => 2, 137 => 2, 144 => 1, 167 => 1
];

$ownerId = 4; // Assuming owner ID 4 based on previous context

echo "--- BULK WAREHOUSE STOCK CORRECTION ---\n";
DB::beginTransaction();
try {
    foreach ($updates as $variantId => $targetQty) {
        $variant = ProductVariant::find($variantId);
        if (!$variant) {
            echo "ERROR: Variant ID $variantId not found.\n";
            continue;
        }

        $sl = StockLocation::firstOrCreate(
            ['user_id' => $ownerId, 'product_variant_id' => $variantId, 'location' => 'warehouse'],
            ['quantity' => 0]
        );

        $oldQty = $sl->quantity;
        $sl->quantity = $targetQty;
        $sl->save();

        // Record Adjustment Movement
        StockMovement::create([
            'user_id' => $ownerId,
            'product_variant_id' => $variantId,
            'movement_type' => 'adjustment',
            'from_location' => null,
            'to_location' => 'warehouse',
            'quantity' => $targetQty - $oldQty,
            'unit_price' => $variant->price, // Optional if needed
            'notes' => "Manual Bulk Correction: Set to $targetQty btls",
            'created_by' => $ownerId,
        ]);

        echo "Updated {$variant->name}: $oldQty -> $targetQty\n";
    }

    DB::commit();
    echo "\nSUCCESS: Bulk update complete.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
}
