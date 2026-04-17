<?php

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Load Laravel Bootstrap
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Starting Historical Realignment (Live Sync) ---\n";

DB::beginTransaction();

try {
    // 1. RELOCATE FINANCIAL HANDOVERS
    // Moving data from 16th -> 15th (Shift 02)
    DB::table('financial_handovers')->whereDate('handover_date', '2026-04-16')->update(['handover_date' => '2026-04-15']);
    // Moving data from 17th -> 16th (Shift 03)
    DB::table('financial_handovers')->whereDate('handover_date', '2026-04-17')->update(['handover_date' => '2026-04-16']);
    echo "✔ Handover dates relocated.\n";

    // 2. RELOCATE WAITER RECONCILIATIONS (Includes Shortages)
    DB::table('waiter_daily_reconciliations')->whereDate('reconciliation_date', '2026-04-16')->update(['reconciliation_date' => '2026-04-15']);
    DB::table('waiter_daily_reconciliations')->whereDate('reconciliation_date', '2026-04-17')->update(['reconciliation_date' => '2026-04-16']);
    echo "✔ Waiter reconciliations relocated.\n";

    // 3. RELOCATE STAFF SHORTAGES (If table exists)
    if (Schema::hasTable('staff_shortages')) {
        DB::table('staff_shortages')->whereDate('shortage_date', '2026-04-16')->update(['shortage_date' => '2026-04-15']);
        DB::table('staff_shortages')->whereDate('shortage_date', '2026-04-17')->update(['shortage_date' => '2026-04-16']);
        echo "✔ Staff shortages relocated.\n";
    }

    // 4. SYNC DAILY CASH LEDGERS
    // We want the 15th and 16th to be 'closed' and correctly dated
    DB::table('daily_cash_ledgers')->whereDate('ledger_date', '2026-04-16')->update(['ledger_date' => '2026-04-15', 'status' => 'closed']);
    DB::table('daily_cash_ledgers')->whereDate('ledger_date', '2026-04-17')->update(['ledger_date' => '2026-04-16', 'status' => 'closed']);
    
    // Ensure April 17 is clean and ready for today
    DB::table('daily_cash_ledgers')->whereDate('ledger_date', '2026-04-17')->delete();
    
    echo "✔ Cash ledgers synchronized.\n";

    DB::commit();
    echo "--- ALIGNMENT COMPLETE! ---\n";
    echo "Your live database is now perfectly synced with your local audit.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "✘ ERROR: " . $e->getMessage() . "\n";
}
