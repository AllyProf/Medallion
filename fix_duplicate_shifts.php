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

echo "--- Medallion Database Cleanup Utility v2 ---\n";

// 1. DELETE DUPLICATE SHIFTS
$idsToDelete = [8, 9, 10, 11];
foreach ($idsToDelete as $id) {
    echo "\n[Shift ID: $id]\n";
    $shift = BarShift::find($id);
    if ($shift) {
        $shift->delete();
        echo ">>> SHIFT DELETED.\n";
    } else {
        echo "Status: Not found.\n";
    }
}

// 2. DEDUPLICATE RECONCILIATIONS
echo "\n--- Deduplicating Reconciliation Records (Hawa Mswaki / ID 47) ---\n";
// Specifically clean up the duplicate entries for Hawa
$reconciliations = WaiterDailyReconciliation::where('waiter_id', 47)
    ->orderBy('created_at', 'asc')
    ->get()
    ->groupBy(function($item) {
        return \Carbon\Carbon::parse($item->reconciliation_date)->format('Y-m-d') . '_' . $item->reconciliation_type . '_' . $item->difference;
    });

foreach ($reconciliations as $groupKey => $group) {
    if ($group->count() > 1) {
        echo "Found " . $group->count() . " duplicates for group $groupKey\n";
        $keep = $group->first();
        echo "Keeping ID: {$keep->id}\n";
        $group->slice(1)->each(function($dup) {
            echo "Deleting duplicate ID: {$dup->id}\n";
            $dup->delete();
        });
    }
}

// 3. REMOVE GHOST DEBT (Cross-midnight leak)
// Hawa's orders happened after midnight but belong to the 18th's shift.
// We remove the entry dated the 19th if the user confirms only one 3500 exists.
echo "\n--- Removing Ghost Debt (Apr 19 Cross-Midnight Leak) ---\n";
$ghost = WaiterDailyReconciliation::where('waiter_id', 47)
    ->whereDate('reconciliation_date', '2026-04-19')
    ->where('difference', -3500)
    ->first();

if ($ghost) {
    echo "Found ghost debt dated Apr 19. Deleting...\n";
    $ghost->delete();
    echo ">>> DELETED.\n";
} else {
    echo "No ghost debt found.\n";
}

echo "\n--- Cleanup Complete ---\n";
