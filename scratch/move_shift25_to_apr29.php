<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;
use Illuminate\Support\Facades\DB;

echo "--- MOVING SHIFT 25 TO APR 29 ---\n";

DB::beginTransaction();
try {
    $shift25 = BarShift::find(25);
    if (!$shift25) {
        echo "Shift 25 not found.\n";
        exit;
    }

    // Change shift opened_at date to Apr 29, keeping the time or setting a new time.
    // The user just said "Apr 29, 2026". Let's set it to 2026-04-29 08:00:00
    // Wait, it was opened at 21:58:07 on the 28th. Let's just make it 2026-04-29 08:00:00
    $shift25->opened_at = '2026-04-29 08:00:00';
    $shift25->save();
    echo "Shift 25 opened_at updated to 2026-04-29 08:00:00\n";

    // Update orders
    $orders = BarOrder::where('bar_shift_id', 25)->get();
    $orderCount = 0;
    foreach ($orders as $order) {
        // Change date to Apr 29, keep time
        $time = date('H:i:s', strtotime($order->created_at));
        $newDate = '2026-04-29 ' . $time;
        $order->created_at = $newDate;
        $order->updated_at = $newDate;
        $order->save();
        $orderCount++;
    }
    echo "Updated $orderCount orders for Shift 25 to Apr 29.\n";

    // Update reconciliations
    $recs = WaiterDailyReconciliation::where('bar_shift_id', 25)->get();
    $recCount = 0;
    foreach ($recs as $rec) {
        $rec->reconciliation_date = '2026-04-29';
        $rec->save();
        $recCount++;
    }
    echo "Updated $recCount reconciliations to Apr 29.\n";

    DB::commit();
    echo "Transaction committed.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit;
}

echo "\n--- SYNCING LEDGERS ---\n";
$l28 = DailyCashLedger::where('ledger_date', '2026-04-28')->first();
if ($l28) {
    $l28->syncTotals()->save();
    echo "Apr 28 Ledger synced.\n";
}

$l29 = DailyCashLedger::where('ledger_date', '2026-04-29')->first();
if ($l29) {
    $l29->syncTotals()->save();
    echo "Apr 29 Ledger synced.\n";
}

echo "Done.\n";
