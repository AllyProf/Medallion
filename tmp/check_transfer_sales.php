<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transfer = \App\Models\StockTransfer::where('transfer_number', 'ST2026040002')->first();
if ($transfer) {
    echo "Transfer ID: {$transfer->id}\n";
    $ts = \App\Models\TransferSale::where('stock_transfer_id', $transfer->id)->get();
    echo "Total Sales: " . $ts->count() . "\n";
    foreach($ts as $t) {
        echo "Sale ID: {$t->id}, Order Item ID: {$t->order_item_id}, Qty: {$t->quantity}, Total Price: {$t->total_price}\n";
    }
} else {
    echo "Transfer not found.\n";
}
