<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$h = App\Models\FinancialHandover::where('bar_shift_id', 5)->first();
if ($h) {
    $b = $h->payment_breakdown;
    if (is_string($b)) $b = json_decode($b, true);
    $b['cash'] = 138000;
    unset($b['shortage_payment']);
    $h->payment_breakdown = $b;
    $h->save();
    echo "Shift 5 fixed.\n";
} else {
    echo "Shift 5 handover not found.\n";
}
