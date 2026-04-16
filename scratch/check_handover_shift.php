<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinancialHandover;
use App\Models\BarOrder;

$ownerId = 4;
$handover = FinancialHandover::where('user_id', $ownerId)
    ->where('amount', 47000)
    ->latest()
    ->first();

if ($handover) {
    echo "Handover ID: {$handover->id}\n";
    echo "Handover Date: {$handover->handover_date}\n";
    echo "Shift ID: {$handover->bar_shift_id}\n";
    
    if ($handover->bar_shift_id) {
        $orders = BarOrder::where('bar_shift_id', $handover->bar_shift_id)
            ->where('status', 'served')
            ->get();
        echo "Orders for this shift: " . $orders->count() . "\n";
        echo "Total Order Value: " . $orders->sum('total_amount') . "\n";
    }
} else {
    echo "No handover found with amount 47,000.\n";
}
