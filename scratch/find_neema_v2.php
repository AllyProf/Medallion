<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staff;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;

echo "--- SEARCHING FOR NEEMA BY FULL NAME ---\n";
$staff = Staff::where('full_name', 'like', '%Neema%')->with('role')->first();

if (!$staff) {
    echo "No staff found with name matching 'Neema'.\n";
    echo "Checking all waiters instead:\n";
    $waiters = Staff::whereHas('role', function($q){ $q->where('slug', 'waiter'); })->get();
    foreach($waiters as $w) echo "  - ID: {$w->id} | Name: {$w->full_name}\n";
    exit;
}

echo "Found: ID: {$staff->id} | Name: {$staff->full_name} | Role: " . ($staff->role->slug ?? 'N/A') . " | Active: " . ($staff->is_active ? 'Yes' : 'No') . " | User ID (Owner): {$staff->user_id}\n";

$today = now()->format('Y-m-d');
$orders = BarOrder::where('waiter_id', $staff->id)
    ->whereDate('created_at', $today)
    ->get();

echo "Orders Today ($today): " . $orders->count() . "\n";
foreach($orders as $o) {
    echo "  - Order ID: {$o->id} | Status: {$o->status} | Payment: {$o->payment_status} | Shift ID: {$o->bar_shift_id}\n";
}

$recs = WaiterDailyReconciliation::where('waiter_id', $staff->id)
    ->whereDate('reconciliation_date', $today)
    ->get();

echo "Reconciliations Today: " . $recs->count() . "\n";
foreach($recs as $r) {
    echo "  - Rec ID: {$r->id} | Type: {$r->reconciliation_type} | Status: {$r->status} | Shift ID: {$r->bar_shift_id}\n";
}
