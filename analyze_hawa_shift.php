<?php

use App\Models\Staff;
use App\Models\BarShift;
use App\Models\WaiterDailyReconciliation;

$hawa = Staff::where('full_name', 'like', '%HAWA MSWAKI%')->first();
$activeShift = BarShift::where('status', 'open')->first(); // Assuming one active shift for now or filtering by user

echo "HAWA STAFF ID: " . ($hawa->id ?? 'NOT FOUND') . "\n";
if ($activeShift) {
    echo "ACTIVE SHIFT: #" . $activeShift->shift_number . " (ID: " . $activeShift->id . ")\n";
    echo "  Status: " . $activeShift->status . "\n";
    echo "  Staff: " . ($activeShift->staff->full_name ?? 'Unknown') . "\n";
}

$reconciliations = WaiterDailyReconciliation::where('reconciliation_date', '>=', '2026-04-19')
    ->with('waiter')
    ->orderBy('reconciliation_date', 'desc')
    ->get();

echo "\n--- RECENT RECONCILIATIONS ---\n";
foreach ($reconciliations as $r) {
    echo "  Date: " . $r->reconciliation_date->format('Y-m-d') . " | ";
    echo "Staff: " . ($r->waiter->full_name ?? 'Unknown') . " | ";
    echo "Expected: " . number_format($r->expected_amount) . " | ";
    echo "Actual: " . number_format($r->actual_amount) . " | ";
    echo "Diff: " . number_format($r->difference) . " | ";
    echo "Status: " . $r->status . "\n";
}
