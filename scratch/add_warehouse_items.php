<?php
/**
 * Add/update warehouse stock from count:
 * Bonne Esperance red 3, Moet Rose 1, M/Water Small 2, Gordons 1, Jameson 1L 1
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

$userId = 4;

DB::transaction(function () use ($userId) {
    // 1) Create Bonne Esperance Red if missing
    $bonne = ProductVariant::where('name', 'like', '%Bonne%Espr%')->first();
    if (!$bonne) {
        $bonne = ProductVariant::create([
            'product_id' => 10, // Martin Champagne / Wines group
            'name' => 'Bonne Esperance Red',
            'measurement' => 750,
            'unit' => 'ml',
            'packaging' => 'Piece',
            'items_per_package' => 1,
            'buying_price_per_unit' => 17000,
            'selling_price_per_unit' => 25000,
            'can_sell_in_tots' => false,
            'selling_type' => 'bottle',
            'selling_price_per_tot' => 0,
            'is_active' => true,
            'counter_alert_threshold' => 10,
        ]);
        echo "CREATED variant #{$bonne->id} Bonne Esperance Red\n";
    } else {
        echo "FOUND Bonne Esperance #{$bonne->id} {$bonne->name}\n";
    }

    // Gordons: no size given → use 750ml (standard bottle)
    $gordons = ProductVariant::where('name', 'Gordons 750ml')->where('product_id', 9)->first()
        ?: ProductVariant::where('name', 'Gordons 750ml')->orderBy('id')->first();

    $targets = [
        [$bonne->id, 'Bonne Esperance Red', 3],
        [114, 'Moët Rosé', 1],
        [28, 'M/Water Small', 2],
        [$gordons->id, 'Gordons 750ml', 1],
        [89, 'Jameson 1L', 1],
    ];

    foreach ($targets as [$variantId, $label, $qty]) {
        $stock = StockLocation::firstOrCreate(
            [
                'user_id' => $userId,
                'product_variant_id' => $variantId,
                'location' => 'warehouse',
            ],
            [
                'quantity' => 0,
                'average_buying_price' => ProductVariant::find($variantId)->buying_price_per_unit ?? 0,
            ]
        );
        $before = (float) $stock->quantity;
        $stock->update(['quantity' => $qty]);
        echo "  SET {$label}: {$before} → {$qty}\n";
    }
});

echo "\nDone. Refresh /bar/counter/stock-sheet/warehouse\n";
