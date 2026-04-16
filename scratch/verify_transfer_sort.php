<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

echo "--- FIRST PAGE TRANSFERS (VERIFYING SORT) ---\n";
// Manually recreate the logic from the controller
$transfers = StockTransfer::where('user_id', 1) // Owner ID is 1 usually
    ->orderByRaw("CASE 
        WHEN status = 'approved' THEN 1 
        WHEN status = 'prepared' THEN 2 
        WHEN status = 'pending' THEN 3 
        WHEN status = 'rejected' THEN 5
        WHEN status = 'completed' THEN 6 
        ELSE 4 END ASC")
    ->orderBy('transfer_number', 'desc')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

foreach ($transfers as $t) {
    echo "ID: {$t->id} | Num: {$t->transfer_number} | Status: {$t->status} | Created: {$t->created_at}\n";
}
