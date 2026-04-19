<?php
/**
 * FINAL DATA ADJUSTMENT SCRIPT
 * Force-moves Shift 4 to April 18 and Shift 5 to April 19.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- STARTING FINAL DATA FIX ---\n";

DB::beginTransaction();

try {
    // 1. Move Shift 4 to April 18
    echo "[1/4] Moving Shift 4 to April 18...\n";
    DB::table('bar_shifts')->where('id', 4)->update([
        'opened_at' => '2026-04-18 08:00:00',
        'closed_at' => '2026-04-18 20:00:00'
    ]);
    DB::table('financial_handovers')->where('bar_shift_id', 4)->update(['handover_date' => '2026-04-18']);
    DB::table('orders')->where('bar_shift_id', 4)->update(['created_at' => DB::raw("CONCAT('2026-04-18 ', TIME(created_at))")]);

    // 2. Move Shift 5 to April 19
    echo "[2/4] Moving Shift 5 to April 19...\n";
    DB::table('bar_shifts')->where('id', 5)->update([
        'opened_at' => '2026-04-19 08:00:00',
        'closed_at' => '2026-04-19 23:59:59'
    ]);
    DB::table('financial_handovers')->where('bar_shift_id', 5)->update(['handover_date' => '2026-04-19']);
    DB::table('orders')->where('bar_shift_id', 5)->update(['created_at' => DB::raw("CONCAT('2026-04-19 ', TIME(created_at))")]);

    // 3. Ensure April 19 Ledger is initialized
    echo "[3/4] Ensuring Ledgers exist...\n";
    $ownerId = DB::table('daily_cash_ledgers')->max('user_id'); // Assuming same owner
    DB::table('daily_cash_ledgers')->updateOrInsert(
        ['ledger_date' => '2026-04-19', 'user_id' => $ownerId],
        ['status' => 'open']
    );

    DB::commit();
    echo "--- SUCCESS: Shifts re-aligned ---\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "--- ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
