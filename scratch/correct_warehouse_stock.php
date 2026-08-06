<?php
/**
 * Keep only listed warehouse items; DELETE all other warehouse stock rows.
 * Run: php scratch/correct_warehouse_stock.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

$userId = 4;

// Only items from the physical count (with clear quantities)
$corrections = [
    'M/Water Small' => 2,
    'Hennessy VSOP 750ml' => 3,
    'Hennessy VS 1L' => 4,
    'Hennessy VS 750ml' => 4,
    'Hennessy VS 200ml' => 1,
    'Martell VSOP' => 3,
    'Martell VS' => 2,
    'J/walker-Black Label 375ml' => 1,
    "Jack Daniel's 1L" => 2,
    "Jack Daniel's 750ml" => 2,
    "Jack Daniel's Honey 1L" => 1,
    "Ballantine's 750ml" => 1,
    'Black & White 200ml' => 2,
    'Jägermeister 350ml' => 2,
    'Absolut Vodka 1L' => 2,
    'Absolut Vodka 750ml' => 3,
    'Absolut Vodka 350ml' => 2,
    'Absolut Vodka 200ml' => 1,
    'Magic Moments Green Apple 750ml' => 1,
    'Magic Moments Chocolate 750ml' => 1,
    'Jameson 1L' => 3,
    'Jameson 375ml' => 1,
    'J & B 750ml' => 1,
    'Grants 1L' => 3,
    'Grants 750ml Glass' => 3,
    'Gilbeys Gin 750' => 4,
    'Sminoff Vodika-Kubwa' => 3,
    'Chrome Gin vodika 750' => 2,
    'Chrome Gin smooth 200' => 1,
    'Campari 1ltr tot' => 2,
    'Famous Grouse 350ml' => 1,
    'Konyagi 750ml' => 10,
    'Hanson Choice 750ml' => 5,
    'Highlife 750ml' => 11,
    'K-Vant 750ml' => 4,
    'K-Vant 200ml' => 14,
    'Martin Champagne 750ml' => 1,
    'Moët Nectar Imperial' => 1,
    'Drostdy Hof CRI White 700ml' => 2,
    'Drostdy Hof CRI White 375ml' => 2,
    'Pearly Bay Dry Red' => 2,
    'Lions Hill Sweet Red' => 2,
    'Nederburg Merlot 750ml' => 2,
    'TZEE lemon and ginger' => 1,
];

$keepIds = [];
$updates = [];

foreach ($corrections as $name => $qty) {
    $variant = ProductVariant::where('name', $name)->first();
    if (!$variant) {
        echo "SKIP missing: {$name}\n";
        continue;
    }
    $keepIds[] = $variant->id;
    $stock = StockLocation::firstOrCreate(
        ['user_id' => $userId, 'product_variant_id' => $variant->id, 'location' => 'warehouse'],
        ['quantity' => 0]
    );
    $updates[] = compact('variant', 'stock', 'qty', 'name') + [
        'before' => (float) $stock->quantity,
    ];
}

$toDelete = StockLocation::with('productVariant')
    ->where('user_id', $userId)
    ->where('location', 'warehouse')
    ->whereNotIn('product_variant_id', $keepIds ?: [0])
    ->get();

echo "UPDATE " . count($updates) . " listed items\n";
echo "DELETE " . $toDelete->count() . " warehouse rows not on list\n\n";

DB::transaction(function () use ($updates, $toDelete) {
    foreach ($updates as $u) {
        $u['stock']->update(['quantity' => $u['qty']]);
        echo "  SET {$u['name']}: {$u['before']} → {$u['qty']}\n";
    }
    foreach ($toDelete as $row) {
        $name = $row->productVariant->name ?? ('variant#' . $row->product_variant_id);
        echo "  DEL {$name} (was qty {$row->quantity})\n";
        $row->delete();
    }
});

$left = StockLocation::where('user_id', $userId)->where('location', 'warehouse')->count();
$withQty = StockLocation::where('user_id', $userId)->where('location', 'warehouse')->where('quantity', '>', 0)->count();
echo "\nDone. Warehouse rows left: {$left} (with qty>0: {$withQty})\n";
echo "Refresh /bar/counter/stock-sheet/warehouse\n";
