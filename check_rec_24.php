<?php

use App\Models\WaiterDailyReconciliation;

$rec = WaiterDailyReconciliation::find(24);
if ($rec) {
    echo "REC #24 DETAILS:\n";
    echo "  Shift ID: " . ($rec->bar_shift_id ?? 'NULL') . "\n";
    echo "  Date: " . $rec->reconciliation_date->format('Y-m-d') . "\n";
    echo "  Difference: " . $rec->difference . "\n";
    echo "  Expected: " . $rec->expected_amount . "\n";
    echo "  Submitted: " . $rec->submitted_amount . "\n";
} else {
    echo "Rec #24 not found.\n";
}
