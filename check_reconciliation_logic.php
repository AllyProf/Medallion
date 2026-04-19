<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ownerId = 4;
$openShifts = \App\Models\BarShift::where('user_id', $ownerId)->where('status', 'open')->get();
echo "OPEN_SHIFTS_COUNT:" . $openShifts->count() . "\n";
foreach($openShifts as $s) {
    echo "SHIFT_ID_" . $s->id . ":" . json_encode($s) . "\n";
}

$priorityHandover = \App\Models\FinancialHandover::where('user_id', $ownerId)
    ->where('handover_type', 'staff_to_accountant')
    ->whereIn('status', ['pending', 'verified'])
    ->orderBy('status', 'asc')
    ->orderBy('handover_date', 'asc')
    ->first();
echo "PRIORITY_HANDOVER:" . json_encode($priorityHandover) . "\n";
