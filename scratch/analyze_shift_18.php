<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\Staff;

$date = '2026-04-24';
$shiftId = 18;

echo "WAITER BREAKDOWN FOR SHIFT #$shiftId ($date):\n";

$waiters = Staff::whereHas('orders', function($q) use ($shiftId) {
    $q->where('bar_shift_id', $shiftId);
})->get();

foreach($waiters as $w) {
    $orders = BarOrder::where('bar_shift_id', $shiftId)
        ->where('waiter_id', $w->id)
        ->where('status', '!=', 'cancelled')
        ->get();
    
    $totalAmount = $orders->sum('total_amount');
    $orderCount = $orders->count();
    
    echo "Staff: {$w->full_name} | Expected (Sum of Orders): TSh " . number_format($totalAmount) . " | Order Count: $orderCount\n";
    foreach($orders as $o) {
        echo "  - {$o->order_number}: TSh " . number_format($o->total_amount) . "\n";
    }
    echo "\n";
}
