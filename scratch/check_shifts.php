<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;

$date = '2026-04-24';
$shifts = BarShift::whereDate('opened_at', $date)->get();

echo "SHIFTS FOR $date:\n";
foreach($shifts as $s) {
    echo "Shift #{$s->id} | Status: {$s->status} | Staff: " . ($s->staff->full_name ?? 'N/A') . " | Orders: " . $s->orders()->count() . "\n";
}
