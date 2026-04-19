<?php
/**
 * MEDALLION - Database Maintenance Utility
 * Purpose: Cleanup duplicate active bar shifts and reconciliation records
 * Usage: php fix_duplicate_shifts.php
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;
use App\Models\FinancialHandover;

echo "--- Medallion Shift & Reconciliation Cleanup Utility ---\n";

// 1. DELETE DUPLICATE SHIFTS (8, 9, 10, 11)
$idsToDelete = [8, 9, 10, 11];

foreach ($idsToDelete as $id) {
    echo "\n[Shift ID: $id]\n";
    $shift = BarShift::find($id);
    
    if (!$shift) {
        echo "Status: Not found.\n";
        continue;
    }

    $orderCount = BarOrder::where('bar_shift_id', $id)->count();
    $recCount = WaiterDailyReconciliation::where('bar_shift_id', $id)->count();
    $handoverCount = FinancialHandover::where('bar_shift_id', $id)->count();

    echo "Data Profile: Orders($orderCount), Recs($recCount), Handovers($handoverCount)\n";
    $shift->delete();
    echo ">>> SHIFT DELETED.\n";
}

// 2. DEDUPLICATE RECONCILIATIONS (WAITERS)
echo "\n--- Deduplicating Reconciliation Records (Hawa Mswaki / ID 47) ---\n";

$reconciliations = WaiterDailyReconciliation::where('waiter_id', 47)
    ->orderBy('created_at', 'asc')
    ->get()
    ->groupBy(function($item) {
        // Group by date and type to find duplicates
        return \Carbon\Carbon::parse($item->reconciliation_date)->format('Y-m-d') . '_' . $item->reconciliation_type . '_' . $item->difference;
    });

foreach ($reconciliations as $groupKey => $group) {
    if ($group->count() > 1) {
        echo "Found " . $group->count() . " duplicates for group $groupKey\n";
        // Keep the first one (oldest created_at), delete the others
        $keep = $group->first();
        echo "Keeping ID: {$keep->id}\n";
        
        $group->slice(1)->each(function($dup) {
            echo "Deleting duplicate ID: {$dup->id}\n";
            $dup->delete();
        });
    }
}

echo "\n--- Cleanup Complete. Everything is now synchronized! ---\n";
