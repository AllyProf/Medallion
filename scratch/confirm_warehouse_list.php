<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductVariant;
use App\Models\StockLocation;

$userId = 4;

// Your physical count list (OCR names → expected qty; null = qty not given)
$expected = [
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
    'Jameson 750ml' => null, // listed, no qty
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
    'Gordons 200ml' => null, // "Gordons" unclear size
    'Gordons 750ml' => null,
    'Konyagi 750ml' => 10,
    'Hanson Choice 750ml' => 5,
    'Highlife 750ml' => 11,
    'K-Vant 750ml' => 4,
    'K-Vant 200ml' => 14,
    'Martin Champagne 750ml' => 1,
    'Moët Nectar Imperial' => 1,
    'Moët Rosé' => null, // "MOET DOSE" guess
    'Drostdy Hof CRI White 700ml' => 2,
    'Drostdy Hof CRI White 375ml' => 2,
    'Pearly Bay Dry Red' => 2,
    'Lions Hill Sweet Red' => 2,
    'Nederburg Merlot 750ml' => 2,
    'Bonne Esperance red' => 3, // may not exist
    'TZEE lemon and ginger' => 1,
];

$current = StockLocation::with('productVariant')
    ->where('user_id', $userId)
    ->where('location', 'warehouse')
    ->get()
    ->keyBy(fn ($r) => $r->productVariant->name ?? '');

echo "=== WAREHOUSE vs YOUR LIST ===\n\n";
echo "OK / MISMATCH / MISSING:\n";
$ok = 0;
$mismatch = 0;
$missing = 0;
$unclear = 0;
$listedNames = [];

foreach ($expected as $name => $qty) {
    $listedNames[] = $name;
    $row = $current->get($name);
    $variant = ProductVariant::where('name', $name)->first();

    if (!$variant && $name === 'Bonne Esperance red') {
        // fuzzy search
        $variant = ProductVariant::where('name', 'like', '%Bonne%')
            ->orWhere('name', 'like', '%Esperance%')
            ->orWhere('name', 'like', '%esprance%')
            ->first();
        echo "  ? {$name}: PRODUCT NOT IN CATALOG" . ($variant ? " (maybe: {$variant->name})" : '') . "\n";
        $missing++;
        continue;
    }

    if ($qty === null) {
        $have = $row ? (float) $row->quantity : 0;
        echo "  ? {$name}: listed but NO QTY given (warehouse now: {$have})\n";
        $unclear++;
        continue;
    }

    if (!$row) {
        echo "  X {$name}: expected {$qty} — NOT IN WAREHOUSE\n";
        $missing++;
        continue;
    }

    $have = (float) $row->quantity;
    if (abs($have - $qty) < 0.01) {
        echo "  ✓ {$name}: {$have}\n";
        $ok++;
    } else {
        echo "  ! {$name}: have {$have}, expected {$qty}\n";
        $mismatch++;
    }
}

echo "\nEXTRA in warehouse (not on your list):\n";
$extra = 0;
foreach ($current as $name => $row) {
    if (!in_array($name, $listedNames, true) && (float) $row->quantity > 0) {
        echo "  + {$name}: {$row->quantity}\n";
        $extra++;
    }
}
if ($extra === 0) {
    echo "  (none)\n";
}

echo "\nSummary: OK={$ok} mismatch={$mismatch} missing={$missing} unclear={$unclear} extra={$extra}\n";
echo "Total warehouse rows: {$current->count()}\n";
