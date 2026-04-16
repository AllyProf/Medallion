<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockReceipt;
use App\Models\StockReceiptItem;

echo "--- Stock Receipt #8 Inspection ---\n";
$receipt = StockReceipt::find(8);
if (!$receipt) {
    echo "Receipt #8 not found.\n";
    exit;
}

foreach ($receipt->items as $item) {
    echo "Variant ID: {$item->product_variant_id} | Qty: {$item->quantity} | Items Per Package: {$item->items_per_package} | Total Units: {$item->total_units}\n";
}
