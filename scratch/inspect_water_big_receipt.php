<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockReceipt;
use App\Models\ProductVariant;

$variant = ProductVariant::where('name', 'like', '%Water Big%')->first();

if ($variant) {
    echo "--- Receipts for {$variant->name} (ID: {$variant->id}) ---\n";
    $receipts = StockReceipt::where('product_variant_id', $variant->id)->get();
    foreach ($receipts as $r) {
        echo "Receipt ID: {$r->id} | Received Date: {$r->received_date} | Qty (Cartons/Units): {$r->quantity_received} | Total Units: {$r->total_units} | Notes: {$r->notes}\n";
    }
} else {
    echo "Water Big variant not found.\n";
}
