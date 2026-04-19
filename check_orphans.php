<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ownerId = 4;
$activeShiftId = 7;
$today = date('Y-m-d');

$orphans = \App\Models\BarOrder::where('user_id', $ownerId)
    ->whereDate('created_at', $today)
    ->where('bar_shift_id', '!=', $activeShiftId)
    ->whereNotNull('bar_shift_id')
    ->get();

echo "ORPHAN_COUNT:" . $orphans->count() . "\n";
foreach($orphans as $o) {
    echo "ORPHAN_ORD_" . $o->order_number . ": ShiftID=" . $o->bar_shift_id . "\n";
}

$nullShiftOrders = \App\Models\BarOrder::where('user_id', $ownerId)
    ->whereDate('created_at', $today)
    ->whereNull('bar_shift_id')
    ->get();
echo "NULL_SHIFT_COUNT:" . $nullShiftOrders->count() . "\n";
