<?php

use App\Models\WaiterDailyReconciliation;

$rec = WaiterDailyReconciliation::find(24);
if ($rec) {
    echo "--- FULL RECONCILIATION DATA (#24) ---\n";
    $data = $rec->toArray();
    foreach ($data as $key => $val) {
        echo "  $key: " . (is_array($val) ? json_encode($val) : $val) . "\n";
    }
} else {
    echo "Rec #24 not found.\n";
}
