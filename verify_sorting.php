<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ownerId = 4;
$allOpenShiftIds = [6]; // The known active shift
$idsString = implode(',', array_map('intval', $allOpenShiftIds));

$orders = \App\Models\BarOrder::where('user_id', $ownerId)
    ->whereNotNull('waiter_id')
    ->orderByRaw("FIELD(bar_shift_id, $idsString) DESC")
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['order_number', 'bar_shift_id', 'created_at']);

echo "SORTED_ORDERS_TOP_5:\n";
foreach($orders as $o) {
    echo $o->order_number . " (Shift: " . $o->bar_shift_id . ") " . $o->created_at . "\n";
}
