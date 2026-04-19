<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$todayOrders = \App\Models\BarOrder::whereDate('created_at', '2026-04-19')->get();
echo "TODAY_ORDERS_COUNT:" . $todayOrders->count() . "\n";
foreach($todayOrders as $o) {
    echo "ID:" . $o->id . " Num:" . $o->order_number . " Shift:" . $o->bar_shift_id . "\n";
}
