<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staff;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;

echo "--- SEARCHING FOR NEEMA ---\n";
$staffList = Staff::where('first_name', 'like', '%Neema%')
    ->orWhere('last_name', 'like', '%Neema%')
    ->with('role')
    ->get();

foreach($staffList as $s) {
    echo "ID: {$s->id} | Name: {$s->first_name} {$s->last_name} | Role: " . ($s->role->slug ?? 'N/A') . " | Active: " . ($s->is_active ? 'Yes' : 'No') . " | Branch: {$s->location_branch}\n";
    
    // Check orders today
    $ordersCount = BarOrder::where('waiter_id', $s->id)
        ->whereDate('created_at', now()->format('Y-m-d'))
        ->count();
    echo "  - Orders Today: $ordersCount\n";
    
    // Check reconciliations today
    $recs = WaiterDailyReconciliation::where('waiter_id', $s->id)
        ->whereDate('reconciliation_date', now()->format('Y-m-d'))
        ->get();
    echo "  - Reconciliations Today: " . $recs->count() . "\n";
    foreach($recs as $r) {
        echo "    * ID: {$r->id} | Type: {$r->reconciliation_type} | Status: {$r->status} | Shift ID: {$r->bar_shift_id}\n";
    }
}

if ($staffList->isEmpty()) {
    echo "No staff found with name 'Neema'.\n";
}
