<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$last = \App\Models\BarOrder::orderBy('id', 'desc')->first();
echo "LAST_ORDER:" . json_encode($last) . "\n";

$all6 = \App\Models\BarOrder::where('bar_shift_id', 6)->get();
echo "SHIFT_6_ORDERS_COUNT:" . $all6->count() . "\n";
foreach($all6 as $o) {
    echo "ID:" . $o->id . " Num:" . $o->order_number . "\n";
}
