<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use Carbon\Carbon;

$ownerId = 1; // Assuming owner ID is 1 based on common patterns in this repo, but let's be safe.
// Let's actually find the owner ID from a known order if possible, 
// or just look at all shifts.

echo "--- RECENT SHIFTS ---\n";
$shifts = BarShift::orderBy('id', 'desc')->take(10)->get();
foreach ($shifts as $s) {
    echo "ID: {$s->id} | Staff: {$s->staff_id} | Status: {$s->status} | Opened: {$s->opening_time} | Closed: {$s->closing_time}\n";
}

echo "\n--- TARGET ORDERS ---\n";
$ord18 = BarOrder::where('order_number', 'ORD-18')->first();
$ord19 = BarOrder::where('order_number', 'ORD-19')->first();

if ($ord18) {
    echo "ORD-18: ID {$ord18->id} | Shift ID: {$ord18->bar_shift_id} | Created: {$ord18->created_at} | Status: {$ord18->status}\n";
} else {
    echo "ORD-18 not found\n";
}

if ($ord19) {
    echo "ORD-19: ID {$ord19->id} | Shift ID: {$ord19->bar_shift_id} | Created: {$ord19->created_at} | Status: {$ord19->status}\n";
} else {
    echo "ORD-19 not found\n";
}

$activeShift = BarShift::where('status', 'open')->orderBy('id', 'desc')->first();
if ($activeShift) {
    echo "\nActive Shift ID found by first(): {$activeShift->id}\n";
} else {
    echo "\nNo active shift found!\n";
}
