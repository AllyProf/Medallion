<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StockTransfer;

$batch = 'ST2026040001';
$transfers = StockTransfer::where('transfer_number', $batch)->get();

echo "Batch: $batch\n";
foreach ($transfers as $t) {
    echo "ID: {$t->id}, ProductVariantID: {$t->product_variant_id}, Name: " . ($t->productVariant->name ?? 'N/A') . ", Qty: {$t->quantity_requested}, Total Units: {$t->total_units}\n";
}
