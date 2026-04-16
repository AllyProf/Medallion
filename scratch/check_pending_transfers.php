<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

echo "--- PENDING TRANSFERS ---\n";
$transfers = StockTransfer::where('status', '!=', 'completed')
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($transfers as $t) {
    echo "ID: {$t->id} | Ref: {$t->transfer_ref} | Status: {$t->status} | Created: {$t->created_at}\n";
}

if ($transfers->isEmpty()) {
    echo "No pending transfers found.\n";
}
