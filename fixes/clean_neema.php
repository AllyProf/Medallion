<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;

echo "=== CLEAN NEEMA SCRIPT ===\n";

// Neema is in the staff table with full_name column
$staff = \App\Models\Staff::where('full_name', 'like', '%neema%')
    ->orWhere('email', 'like', '%neema%')
    ->first();

if (!$staff) {
    echo "Not found in staff! Listing ALL staff:\n";
    $allStaff = DB::table('staff')->select('id','full_name','email')->get();
    foreach ($allStaff as $s) {
        echo "  ID={$s->id} | {$s->full_name} | {$s->email}\n";
    }
    exit(1);
}

echo "Found: ID={$staff->id} | {$staff->full_name} | {$staff->email}\n";

$date = date('Y-m-d');
echo "Deleting all data for date: {$date}\n\n";

// Delete orders - table is called 'orders' not 'bar_orders'
$orders = DB::table('orders')->where('waiter_id', $staff->id)->whereDate('created_at', $date)->get();
$count = 0;
foreach ($orders as $order) {
    DB::table('order_payments')->where('order_id', $order->id)->delete();
    DB::table('order_items')->where('order_id', $order->id)->delete();
    DB::table('kitchen_order_items')->where('order_id', $order->id)->delete();
    DB::table('orders')->where('id', $order->id)->delete();
    $count++;
    echo "Deleted order: {$order->order_number}\n";
}
echo ">> Total orders deleted: {$count}\n\n";

// Delete reconciliation records - use user_id (which stores staff_id in this system)
$r = WaiterDailyReconciliation::where('user_id', $staff->id)
    ->whereDate('reconciliation_date', $date)->delete();
echo ">> Deleted reconciliation records: {$r}\n";

// Delete handover records
try {
    $h = DB::table('bar_counter_handovers')
        ->where(function($q) use ($staff) {
            $q->where('staff_id', $staff->id)->orWhere('user_id', $staff->id);
        })
        ->whereDate('date', $date)->delete();
    echo ">> Deleted handover records: {$h}\n";
} catch (\Exception $e) {
    // Try alternative table name
    try {
        $h = DB::table('counter_handovers')
            ->where('user_id', $staff->id)
            ->whereDate('date', $date)->delete();
        echo ">> Deleted handover records: {$h}\n";
    } catch (\Exception $e2) {
        echo ">> Handover delete skipped: " . $e2->getMessage() . "\n";
    }
}

// Also reset any bar shifts for today
try {
    $shifts = DB::table('bar_shifts')
        ->where('staff_id', $staff->id)
        ->whereDate('created_at', $date)
        ->get();
    foreach ($shifts as $shift) {
        DB::table('bar_orders')->where('bar_shift_id', $shift->id)->update(['bar_shift_id' => null]);
    }
    $deletedShifts = DB::table('bar_shifts')
        ->where('staff_id', $staff->id)
        ->whereDate('created_at', $date)
        ->delete();
    echo ">> Reset {$deletedShifts} bar shifts.\n";
} catch (\Exception $e) {
    echo ">> Shift reset skipped: " . $e->getMessage() . "\n";
}

echo "\n=== DONE! Neema can now start fresh. ===\n";
