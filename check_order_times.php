<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orders = \App\Models\BarOrder::whereIn('order_number', ['ORD-113', 'ORD-165'])->get(['order_number', 'created_at', 'bar_shift_id']);
foreach($orders as $o) {
    echo $o->order_number . " : " . $o->created_at . " Shift:" . $o->bar_shift_id . "\n";
}
