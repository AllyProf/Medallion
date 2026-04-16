<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

echo "--- RECENT TRANSFERS ---\n";
$transfers = StockTransfer::orderBy('created_at', 'desc')
    ->take(30)
    ->with('productVariant.product')
    ->get();

foreach ($transfers as $t) {
    if (!$t->productVariant) continue;
    echo "ID: {$t->id} | Date: {$t->created_at} | Item: {$t->productVariant->product->name} | Qty: {$t->total_units} | Status: {$t->status}\n";
}
