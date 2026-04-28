<?php
// check_neema_recs.php - List all WaiterDailyReconciliation records for Neema
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WaiterDailyReconciliation;
use App\Models\Staff;

$neema = Staff::where('full_name', 'like', '%Neema%')->first();
echo "Neema ID: {$neema->id}\n\n";

// Try by waiter_id
$recs = WaiterDailyReconciliation::where('waiter_id', $neema->id)->get();
echo "By waiter_id ({$neema->id}): " . $recs->count() . " records\n";
foreach ($recs as $r) {
    echo "  ID={$r->id} Date={$r->reconciliation_date} Exp={$r->expected_amount} Sub={$r->submitted_amount} Diff={$r->difference} Status={$r->status}\n";
}

// Try by user_id
$recs2 = WaiterDailyReconciliation::where('user_id', $neema->id)->get();
echo "\nBy user_id ({$neema->id}): " . $recs2->count() . " records\n";
foreach ($recs2 as $r) {
    echo "  ID={$r->id} Date={$r->reconciliation_date} Exp={$r->expected_amount} Sub={$r->submitted_amount} Diff={$r->difference} Status={$r->status}\n";
}

// Also show the IDs we fixed (40,44,50,54,59) 
echo "\nFixed IDs (40,44,50,54,59):\n";
foreach ([40,44,50,54,59] as $id) {
    $r = WaiterDailyReconciliation::find($id);
    if ($r) echo "  ID={$r->id} WaiterID={$r->waiter_id} UserID={$r->user_id} Date={$r->reconciliation_date} Exp={$r->expected_amount} Sub={$r->submitted_amount} Diff={$r->difference} Status={$r->status}\n";
}
