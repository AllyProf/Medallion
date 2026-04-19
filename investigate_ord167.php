<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = \App\Models\BarOrder::where('order_number', 'ORD-167')->first();
if ($order) {
    echo "ORDER_DATA:" . json_encode($order) . "\n";
    $shift = \App\Models\BarShift::find($order->bar_shift_id);
    echo "SHIFT_DATA:" . json_encode($shift) . "\n";
    
    $activeShift = \App\Models\BarShift::where('status', 'open')->orderBy('opened_at', 'desc')->first();
    echo "ACTIVE_SHIFT_DATA:" . json_encode($activeShift) . "\n";
} else {
    echo "ORDER_NOT_FOUND\n";
}
