<?php
/**
 * Delete ALL warehouse stock rows (location = warehouse).
 * Counter stock is NOT touched.
 *
 * Dry-run (default):  php scratch/delete_all_warehouse_stock.php
 * Actually delete:    php scratch/delete_all_warehouse_stock.php --confirm
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

$userId = 4; // Medallion owner
$confirm = in_array('--confirm', $argv ?? [], true);

$rows = StockLocation::where('user_id', $userId)
    ->where('location', 'warehouse')
    ->with(['productVariant.product'])
    ->orderBy('id')
    ->get();

echo "Warehouse stock rows for user_id={$userId}: {$rows->count()}\n";
echo str_repeat('-', 72) . "\n";

$totalQty = 0;
foreach ($rows as $row) {
    $variant = $row->productVariant;
    $name = $variant
        ? trim(($variant->product->name ?? '') . ' / ' . ($variant->name ?? ''))
        : '(missing variant)';
    $qty = (float) $row->quantity;
    $totalQty += $qty;
    echo sprintf(
        "  stock#%d  variant#%s  qty=%s  %s\n",
        $row->id,
        $row->product_variant_id,
        $qty,
        $name
    );
}

echo str_repeat('-', 72) . "\n";
echo "Total quantity units: {$totalQty}\n";

if (!$confirm) {
    echo "\nDRY RUN only — nothing deleted.\n";
    echo "To delete all warehouse stock rows, run:\n";
    echo "  php scratch/delete_all_warehouse_stock.php --confirm\n";
    exit(0);
}

DB::transaction(function () use ($userId, $rows) {
    $deleted = StockLocation::where('user_id', $userId)
        ->where('location', 'warehouse')
        ->delete();
    echo "\nDeleted {$deleted} warehouse stock row(s).\n";
});

$remaining = StockLocation::where('user_id', $userId)
    ->where('location', 'warehouse')
    ->count();

echo "Remaining warehouse rows: {$remaining}\n";
echo "Done.\n";
