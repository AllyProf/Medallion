<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

$latest = StockTransfer::orderBy('id', 'desc')->first();
if (!$latest) {
    echo "No transfers found.\n";
    exit;
}

$ownerId = $latest->user_id;
echo "Owner ID: $ownerId\n";

$transfers = StockTransfer::where('user_id', $ownerId)
    ->orderByRaw("CASE 
        WHEN status = 'approved' THEN 1 
        WHEN status = 'prepared' THEN 2 
        WHEN status = 'pending' THEN 3 
        WHEN status = 'rejected' THEN 5
        WHEN status = 'completed' THEN 6 
        ELSE 4 END ASC")
    ->orderBy('transfer_number', 'desc')
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

echo "--- TOP 50 TRANSFERS ---\n";
foreach ($transfers as $t) {
    echo "ID: {$t->id} | Status: " . str_pad($t->status, 10) . " | Num: {$t->transfer_number} | Item: " . ($t->productVariant->product->name ?? 'N/A') . "\n";
}
