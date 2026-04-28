<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = date('Y-m-d');
echo "=== TODAY's ORDERS ({$date}) ===\n";

// List ALL orders today from the 'orders' table
$orders = DB::table('orders')->whereDate('created_at', $date)->get(['id','order_number','waiter_id','user_id','total_amount','payment_status','status']);

if ($orders->isEmpty()) {
    echo "NO orders found today in 'orders' table!\n";
    
    // Check if the table name is different
    $tables = DB::select("SHOW TABLES LIKE '%order%'");
    echo "\nTables containing 'order':\n";
    foreach ($tables as $t) { echo "  " . array_values((array)$t)[0] . "\n"; }
    
    $tables2 = DB::select("SHOW TABLES LIKE '%shift%'");
    echo "\nTables containing 'shift':\n";
    foreach ($tables2 as $t) { echo "  " . array_values((array)$t)[0] . "\n"; }
} else {
    echo "Found " . $orders->count() . " orders:\n";
    foreach ($orders as $o) {
        $waiter = DB::table('staff')->where('id', $o->waiter_id)->first(['full_name']);
        $waiterName = $waiter ? $waiter->full_name : '??';
        echo "  #{$o->order_number} | WaiterID:{$o->waiter_id}({$waiterName}) | Amt:{$o->total_amount} | Status:{$o->payment_status}\n";
    }
}

echo "\n=== NEEMA STAFF ID ===\n";
$neema = DB::table('staff')->where('full_name', 'like', '%neema%')->orWhere('email','like','%neema%')->first();
echo $neema ? "ID={$neema->id} | {$neema->full_name}\n" : "NOT FOUND\n";
