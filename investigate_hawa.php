<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarOrder;
use App\Models\BarShift;

$hawaId = 47;
$orders = BarOrder::where('waiter_id', $hawaId)
    ->where('payment_status', '!=', 'paid')
    ->get();

echo "Unpaid Orders for Hawa (ID: $hawaId):\n";
foreach ($orders as $o) {
    echo "ID: {$o->id}, Date: {$o->created_at}, Total: {$o->total_amount}, Paid: {$o->paid_amount}, Shift ID: {$o->bar_shift_id}, Status: {$o->status}\n";
}

$shift7 = BarShift::find(7);
if ($shift7) {
    echo "\nShift 7 Profile:\n";
    echo "Opened at: {$shift7->opened_at}\n";
    echo "Status: {$shift7->status}\n";
}
