<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shiftId = 6;
$orders = \App\Models\BarOrder::where('bar_shift_id', $shiftId)->get();
echo "SHIFT_6_TOTAL_ORDERS:" . $orders->count() . "\n";
foreach($orders as $o) {
    $waiter = \App\Models\Staff::find($o->waiter_id);
    echo "ORDER:" . $o->order_number . " Amt:" . $o->total_amount . " Waiter:" . ($waiter->full_name ?? 'N/A') . " ID:" . ($waiter->id ?? 'N/A') . "\n";
}
