<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shift6 = \App\Models\BarShift::find(6);
echo "SHIFT_6_DATA:" . json_encode($shift6) . "\n";

$allShifts = \App\Models\BarShift::orderBy('id', 'asc')->get();
foreach($allShifts as $s) {
    echo "ID:" . $s->id . " Status:" . $s->status . "\n";
}
