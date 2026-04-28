<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;

$waiterId = 37; // Miriam
$date = '2026-04-24';
$orders = BarOrder::where('waiter_id', $waiterId)->whereDate('created_at', $date)->get();

echo "TOTAL ORDERS FOR MIRIAM ON $date: " . $orders->count() . "\n";
foreach($orders as $o) {
    echo "Order: {$o->order_number} | Amount: " . number_format($o->total_amount) . " | Status: {$o->status} | Payment: {$o->payment_status} | Shift ID: " . ($o->bar_shift_id ?? 'NULL') . "\n";
}
