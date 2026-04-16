<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockLocation;

$variantId = 28;
$sl = StockLocation::where('product_variant_id', $variantId)
    ->where('location', 'warehouse')
    ->first();

if ($sl) {
    $oldQty = $sl->quantity;
    $sl->update(['quantity' => 24.00]);
    echo "Warehouse stock for M/Water Small (ID $variantId) adjusted from $oldQty to 24.00 bottles (2 Cartons).\n";
} else {
    echo "ERROR: Warehouse stock location not found for Variant $variantId.\n";
}
