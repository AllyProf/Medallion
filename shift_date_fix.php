<?php

/**
 * SHIFT DATE ADJUSTMENT SCRIPT
 * Purpose: Moves Shift 4 and all related financial data from April 17 to April 18.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\BarShift;
use App\Models\FinancialHandover;
use App\Models\WaiterDailyReconciliation;
use App\Models\BarOrder;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shiftId = 4;
$newDate = '2026-04-18';

echo "--- STARTING SHIFT MOVE ($shiftId to $newDate) ---\n";

DB::beginTransaction();

try {
    // 1. Update Bar Shift
    $shift = BarShift::find($shiftId);
    if ($shift) {
        $oldDate = $shift->opened_at->format('Y-m-d');
        echo "[1/4] Found Shift $shiftId (Old Date: $oldDate). Updating to $newDate...\n";
        
        $shift->opened_at = $newDate . ' 08:00:00';
        $shift->closed_at = $newDate . ' 23:59:59';
        $shift->save();
    } else {
        throw new Exception("Shift $shiftId not found.");
    }

    // 2. Update Financial Handover
    $handover = FinancialHandover::where('bar_shift_id', $shiftId)->first();
    if ($handover) {
        echo "[2/4] Found Handover. Updating date to $newDate...\n";
        $handover->handover_date = $newDate;
        $handover->save();
    }

    // 3. Update Waiter Reconciliations
    $recs = WaiterDailyReconciliation::where('bar_shift_id', $shiftId)->get();
    echo "[3/4] Found " . $recs->count() . " waiter reconciliations. Updating dates...\n";
    foreach ($recs as $rec) {
        $rec->save(); 
    }

    // 4. Update Orders
    $ordersCount = BarOrder::where('bar_shift_id', $shiftId)->count();
    echo "[4/4] Shift contains $ordersCount orders. Syncing dates...\n";
    BarOrder::where('bar_shift_id', $shiftId)->update([
        'created_at' => DB::raw("CONCAT('$newDate ', TIME(created_at))"),
        'updated_at' => DB::raw("CONCAT('$newDate ', TIME(updated_at))")
    ]);

    DB::commit();
    echo "--- SUCCESS: Shift $shiftId moved to $newDate ---\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "--- ERROR: " . $e->getMessage() . " ---\n";
    exit(1);
}
