<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

$ownerId = StockTransfer::max('user_id'); // Just picking a user ID that exists

echo "--- RECENT TRANSFERS TO COUNTER ---\n";
$transfers = StockTransfer::where('to_location', 'counter')
    ->orderBy('created_at', 'desc')
    ->take(20)
    ->with('productVariant.product')
    ->get();

foreach ($transfers as $t) {
    if (!$t->productVariant) continue;
    echo "ID: {$t->id} | Date: {$t->created_at} | Item: {$t->productVariant->product->name} | Qty: {$t->quantity} | Status: {$t->status}\n";
}
