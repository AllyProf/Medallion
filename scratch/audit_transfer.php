<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockTransfer;

$t = StockTransfer::where('transfer_number', 'ST2026040009')->first();
if ($t) {
    echo "Transfer: ST2026040009\n";
    echo "Status: {$t->status}\n";
    echo "Approved By (User ID): {$t->approved_by}\n";
    echo "Approved By Staff ID: {$t->approved_by_staff_id}\n";
    echo "Approved At: {$t->approved_at}\n";
    echo "Requested By Staff ID: {$t->requested_by_staff_id}\n";
    echo "Completed Date (Updated At): {$t->updated_at}\n";
} else {
    echo "Transfer not found.\n";
}
