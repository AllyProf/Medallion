<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;

$date = '2026-04-24';
$orders = BarOrder::whereDate('created_at', $date)->get();

echo "ORDERS FOR $date:\n";
foreach($orders as $o) {
    echo "Order: {$o->order_number} | Waiter: " . ($o->waiter->full_name ?? 'N/A') . " | Amount: {$o->total_amount} | Shift ID: " . ($o->bar_shift_id ?? 'NULL') . "\n";
}
