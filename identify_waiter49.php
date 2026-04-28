<?php
// identify_waiter49.php - Find who waiter_id=49 is
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;

// Who is staff ID 49?
$s = Staff::find(49);
echo "Staff ID 49: " . ($s ? $s->full_name . " | " . $s->email : "NOT FOUND") . "\n\n";

// Who is staff ID 39?
$s2 = Staff::find(39);
echo "Staff ID 39: " . ($s2 ? $s2->full_name . " | " . $s2->email : "NOT FOUND") . "\n\n";

// Check what the WaiterDailyReconciliation waiter relationship is
$rec = WaiterDailyReconciliation::with('waiter')->find(44);
if ($rec) {
    echo "Rec ID 44: waiter_id={$rec->waiter_id}\n";
    echo "waiter->full_name: " . ($rec->waiter ? $rec->waiter->full_name : "NULL") . "\n";
}

// Also check Miriam's record
$miriam = Staff::where('full_name', 'like', '%Miriam%')->first();
echo "\nMiriam: " . ($miriam ? "ID={$miriam->id} | {$miriam->full_name}" : "NOT FOUND") . "\n";
$mRecs = WaiterDailyReconciliation::where('waiter_id', $miriam->id ?? 0)->whereDate('reconciliation_date', '2026-04-24')->get();
foreach ($mRecs as $r) {
    echo "  Miriam Rec ID={$r->id} Exp={$r->expected_amount} Sub={$r->submitted_amount} Diff={$r->difference} Status={$r->status}\n";
}
