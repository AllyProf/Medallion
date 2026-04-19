<?php
/**
 * ULTIMATE SHIFT SLIDER SCRIPT
 * Purpose: Slides all historical shifts forward by 1 day to fill the April 17 gap.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- STARTING ULTIMATE SHIFT SLIDE ---\n";

DB::beginTransaction();

try {
    $mapping = [
        2 => '2026-04-16', // Shift 2 (was 15th) -> 16th
        3 => '2026-04-17', // Shift 3 (was 16th) -> 17th (FILL GAP!)
        4 => '2026-04-18', // Shift 4 (was 17th) -> 18th (AS REQUESTED)
        5 => '2026-04-19', // Shift 5 (was 18th) -> 19th (TODAY)
    ];

    foreach ($mapping as $id => $date) {
        echo "[*] Processing Shift $id -> $date\n";
        
        // Update Shift
        DB::table('bar_shifts')->where('id', $id)->update([
            'opened_at' => $date . ' 09:00:00',
            'closed_at' => $date . ' 23:59:59'
        ]);
        
        // Update Handover
        DB::table('financial_handovers')->where('bar_shift_id', $id)->update([
            'handover_date' => $date
        ]);
        
        // Update Orders (Table name is 'orders' per BarOrder model)
        DB::table('orders')->where('bar_shift_id', $id)->update([
            'created_at' => DB::raw("CONCAT('$date ', TIME(created_at))"),
            'updated_at' => DB::raw("CONCAT('$date ', TIME(updated_at))")
        ]);
        
        // Update Waiter Reconciliations
        DB::table('waiter_daily_reconciliations')->where('bar_shift_id', $id)->update([
            'updated_at' => DB::raw("CONCAT('$date ', TIME(updated_at))")
        ]);
    }

    // Special: Move Recovery Pay for April 19
    // Since Shift 5 is on the 19th, these should naturally align now.
    
    DB::commit();
    echo "--- SUCCESS: History Slid Forward ---\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "--- ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
