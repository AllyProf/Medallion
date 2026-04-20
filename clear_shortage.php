<?php

use App\Models\WaiterDailyReconciliation;

$rec = WaiterDailyReconciliation::find(24);
if ($rec) {
    // Sync the difference to 0 to match expected vs submitted
    $rec->update(['difference' => 0]);
    echo "SUCCESS: Shortage cleared for Hawa Mswaki (Rec ID #24).\n";
} else {
    echo "ERROR: Could not find Reconciliation record #24.\n";
}
