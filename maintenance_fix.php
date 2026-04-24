<?php
/**
 * MEDALLION RESTAURANT - LIVE MAINTENANCE SCRIPT
 * This script fixes the specific data issues:
 * 1. Moves Order ORD-382 to the correct active shift.
 * 2. Deletes duplicate open shifts (S000019, S000020).
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your project root folder on the live server.
 * 2. Run it via terminal: php maintenance_fix.php
 * 3. Delete this file after successful execution for security.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\BarShift;
use Illuminate\Support\Facades\DB;

echo "--- Medallion Live Maintenance Tool ---\n";

// --- TASK 1: MOVE ORDER ORD-382 ---
$orderNumber = 'ORD-382';
$order = BarOrder::where('order_number', $orderNumber)->first();

if ($order) {
    // Find the active shift (usually S000018 or whichever is open)
    $activeShift = BarShift::where('status', 'open')->orderBy('id', 'asc')->first();
    
    if ($activeShift) {
        $oldShift = $order->bar_shift_id;
        $order->bar_shift_id = $activeShift->id;
        $order->save();
        echo "TASK 1: Order $orderNumber moved from Shift ID $oldShift to active Shift ID {$activeShift->id}.\n";
    } else {
        echo "TASK 1 WARNING: No active shift found to move the order to.\n";
    }
} else {
    echo "TASK 1: Order $orderNumber not found.\n";
}

// --- TASK 2: CLEANUP DUPLICATE SHIFTS ---
// We search by IDs if we know them, or by logic if labels match.
// Based on your report, we need to remove the extra shifts that were opened accidentally.
// Usually, we keep the one that matches the sequence or the first one opened.
$duplicateIds = [19, 20]; // Adjust these IDs if they differ on Live

foreach ($duplicateIds as $id) {
    $shift = BarShift::find($id);
    if ($shift) {
        echo "TASK 2: Deleting Duplicate Shift ID: $id (Status: {$shift->status})\n";
        $shift->delete();
    } else {
        echo "TASK 2: Shift ID $id not found.\n";
    }
}

echo "--- Maintenance Finished ---\n";
