<?php
/**
 * MEDALLION - Database Maintenance Utility
 * Purpose: Cleanup duplicate active bar shifts (S000008, S000009, S000010, S000011)
 * Usage: php fix_duplicate_shifts.php
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;
use App\Models\FinancialHandover;

// The specific duplicate IDs identified by the user to be removed
$idsToDelete = [8, 9, 10, 11];

echo "--- Medallion Shift Cleanup Utility ---\n";

foreach ($idsToDelete as $id) {
    echo "\n[Shift ID: $id]\n";
    $shift = BarShift::find($id);
    
    if (!$shift) {
        echo "Status: Not found. (Already cleaned or ID incorrect)\n";
        continue;
    }

    // Safety checks for tied production data
    $orderCount = BarOrder::where('bar_shift_id', $id)->count();
    $recCount = WaiterDailyReconciliation::where('bar_shift_id', $id)->count();
    $handoverCount = FinancialHandover::where('bar_shift_id', $id)->count();

    echo "Data Profile:\n";
    echo "- Orders: $orderCount\n";
    echo "- Reconciliations: $recCount\n";
    echo "- Handovers: $handoverCount\n";

    if ($orderCount == 0 && $recCount == 0 && $handoverCount == 0) {
        echo "Result: CLEAN. Proceeding with safe deletion...\n";
        $shift->delete();
        echo ">>> DELETED SUCCESSFULLY.\n";
    } else {
        echo "Result: ACTIVE DATA DETECTED.\n";
        echo "Action: FORCED DELETE (As per Accountant Request)...\n";
        $shift->delete();
        echo ">>> DELETED (Force mode applied).\n";
    }
}

echo "\n--- Cleanup Complete. Shift 07 is now the primary active shift. ---\n";
