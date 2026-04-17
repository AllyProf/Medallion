<?php

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Load Laravel Bootstrap
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Starting Precision History Split ---\n";

DB::beginTransaction();

try {
    // 1. SPLIT FINANCIAL HANDOVERS BY AMOUNT
    // Shift 2 (Total ~376,500) -> Move to April 15
    DB::table('financial_handovers')
        ->whereIn('amount', [130500, 246000, 376500])
        ->update(['handover_date' => '2026-04-15']);

    // Shift 3 (Total ~136,000) -> Move to April 16
    DB::table('financial_handovers')
        ->where('amount', 136000)
        ->update(['handover_date' => '2026-04-16']);

    echo "✔ Financial handovers split by amount.\n";

    // 2. ALIGN WAITER RECONCILIATIONS
    // Move Neema's specific record to April 15 (Matched by her ID and amount -1000)
    $neema = DB::table('staff')->where('full_name', 'LIKE', 'NEEMA%')->first();
    if ($neema) {
        DB::table('waiter_daily_reconciliations')
            ->where('waiter_id', $neema->id)
            ->where('difference', -1000)
            ->update(['reconciliation_date' => '2026-04-15']);
    }

    // Assign all other reconciliations based on the shift flow
    // Reconciliations that sum to the 16th's old total should move to 15th
    // We'll move everything from 16th to 15th, and 17th to 16th to reset
    DB::table('waiter_daily_reconciliations')->whereDate('reconciliation_date', '2026-04-16')->update(['reconciliation_date' => '2026-04-15']);
    DB::table('waiter_daily_reconciliations')->whereDate('reconciliation_date', '2026-04-17')->update(['reconciliation_date' => '2026-04-16']);

    echo "✔ Waiter records reassigned.\n";

    // 3. FORCE CLOSURE ON LEDGERS
    // Ensure 15th and 16th exist as closed
    DB::table('daily_cash_ledgers')->updateOrInsert(
        ['ledger_date' => '2026-04-15'],
        ['user_id' => 2, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]
    );
    DB::table('daily_cash_ledgers')->updateOrInsert(
        ['ledger_date' => '2026-04-16'],
        ['user_id' => 2, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]
    );
    
    // Cleanup 17th
    DB::table('daily_cash_ledgers')->whereDate('ledger_date', '2026-04-17')->delete();

    DB::commit();
    echo "--- PRECISION SPLIT COMPLETE! ---\n";
    echo "Money is now correctly assigned: S2 -> 15th | S3 -> 16th.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "✘ ERROR: " . $e->getMessage() . "\n";
}
