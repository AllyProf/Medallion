<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$o166 = \App\Models\BarOrder::where('order_number', 'ORD-166')->first();
$o167 = \App\Models\BarOrder::where('order_number', 'ORD-167')->first();
$shift6 = \App\Models\BarShift::find(6);

echo "ORD_166:" . ($o166 ? json_encode($o166) : "NULL") . "\n";
echo "ORD_167:" . ($o167 ? json_encode($o167) : "NULL") . "\n";
echo "SHIFT_6:" . ($shift6 ? json_encode($shift6) : "NULL") . "\n";

$openShifts = \App\Models\BarShift::where('status', 'open')->get();
echo "OPEN_SHIFTS_ALL:\n";
foreach($openShifts as $s) {
    echo "ID: " . $s->id . " Status: " . $s->status . "\n";
}
