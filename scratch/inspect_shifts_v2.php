<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;

echo "--- OPEN SHIFTS ---\n";
$shifts = BarShift::where('status', 'open')->get();
foreach ($shifts as $s) {
    $orderCount = BarOrder::where('bar_shift_id', $s->id)->count();
    $latestOrder = BarOrder::where('bar_shift_id', $s->id)->orderBy('created_at', 'desc')->first();
    echo "ID: {$s->id} | Staff: {$s->staff_id} ({$s->staff->full_name}) | Opened: {$s->created_at} | Total Orders: {$orderCount} | Latest Order: " . ($latestOrder ? $latestOrder->created_at : "N/A") . "\n";
}

echo "\n--- SYSTEM TIME ---\n";
echo "Now: " . now() . "\n";
