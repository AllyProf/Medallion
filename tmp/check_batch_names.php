<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StockTransfer;

$batch = 'ST2026040001';
$transfers = StockTransfer::where('transfer_number', $batch)->get();

foreach ($transfers as $t) {
    echo "ID: {$t->id}\n";
    echo "Transfer Variant Name: " . ($t->productVariant->name ?? 'N/A') . "\n";
    echo "Transfer Product Name: " . ($t->productVariant->product->name ?? 'N/A') . "\n";
    echo "-------------------\n";
}
