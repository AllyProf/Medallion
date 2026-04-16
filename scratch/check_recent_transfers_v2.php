<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

echo "--- RECENT TRANSFERS ---\n";
$transfers = StockTransfer::orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

foreach ($transfers as $t) {
    echo "ID: {$t->id} | Ref: {$t->transfer_ref} | Num: {$t->transfer_number} | Status: {$t->status} | Item: " . ($t->productVariant->name ?? 'N/A') . " | Created: {$t->created_at}\n";
}
