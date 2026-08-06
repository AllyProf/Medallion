<?php
/**
 * Clear Camino Tot counter stock to empty (qty 0 + remove open bottle).
 * Run: php scratch/clear_camino_tot.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OpenBottle;
use App\Models\ProductVariant;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

$variant = ProductVariant::where('name', 'like', '%Camino Tot%')->first();
if (!$variant) {
    echo "ERROR: Camino Tot variant not found.\n";
    exit(1);
}

echo "Clearing Camino Tot (variant#{$variant->id})...\n";

DB::transaction(function () use ($variant) {
    $counter = StockLocation::where('product_variant_id', $variant->id)
        ->where('location', 'counter')
        ->get();

    foreach ($counter as $row) {
        echo "  BEFORE stock#{$row->id}: qty={$row->quantity}\n";
        $row->update(['quantity' => 0]);
        echo "  AFTER  stock#{$row->id}: qty=0\n";
    }

    $opens = OpenBottle::where('product_variant_id', $variant->id)->get();
    foreach ($opens as $open) {
        echo "  Deleting open bottle #{$open->id} (tots_remaining={$open->tots_remaining})\n";
        $open->delete();
    }
});

echo "Done. Camino Tot counter stock is empty.\n";
