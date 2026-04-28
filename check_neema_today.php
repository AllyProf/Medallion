<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;
$neema = Staff::where('full_name', 'like', '%Neema%')->first();
if ($neema) {
    $r = WaiterDailyReconciliation::where('waiter_id', $neema->id)
        ->whereDate('reconciliation_date', '2026-04-28')
        ->first();
    if ($r) {
        echo "ID: {$r->id} | Exp: {$r->expected_amount} | Sub: {$r->submitted_amount} | Diff: {$r->difference} | Status: {$r->status}\n";
    } else {
        echo "No record for today.\n";
    }
}
