<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = App\Models\BarOrder::where('bar_shift_id', 5)->count();
echo "Shift 5 Orders Count: " . $count . "\n";

$h = App\Models\FinancialHandover::where('bar_shift_id', 5)->first();
if ($h) {
    echo "Handover ID: " . $h->id . ", Amount: " . $h->amount . "\n";
} else {
    echo "No handover for Shift 5\n";
}
