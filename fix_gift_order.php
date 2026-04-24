<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\BarShift;

echo "--- Fixing Gift John's Order (ORD-382) ---\n";

$order = BarOrder::where('order_number', 'ORD-382')->first();

if ($order) {
    // Find the current active open shift
    $activeShift = BarShift::where('status', 'open')->first();
    
    if ($activeShift) {
        $order->bar_shift_id = $activeShift->id;
        $order->save();
        echo "SUCCESS: Order ORD-382 has been moved to active Shift ID: {$activeShift->id}\n";
    } else {
        echo "ERROR: No active open shift found. Please open a shift first.\n";
    }
} else {
    echo "ERROR: Order ORD-382 not found.\n";
}
