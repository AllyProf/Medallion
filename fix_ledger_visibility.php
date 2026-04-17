<?php

use Illuminate\Support\Facades\DB;

// Load Laravel Bootstrap
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Fixing Ledger Visibility ---\n";

try {
    // 1. Detect the Correct Owner ID from April 17
    $todayLedger = DB::table('daily_cash_ledgers')->whereDate('ledger_date', '2026-04-17')->first();
    
    if (!$todayLedger) {
        throw new Exception("Could not find today's ledger to detect your account ID.");
    }
    
    $correctOwnerId = $todayLedger->user_id;
    echo "✔ Detected your Account ID as: {$correctOwnerId}\n";

    // 2. Update April 15 and 16 to match this ID
    DB::table('daily_cash_ledgers')
        ->whereIn('ledger_date', ['2026-04-15', '2026-04-16'])
        ->update(['user_id' => $correctOwnerId, 'status' => 'closed']);
        
    echo "✔ Visibility restored for April 15 and 16.\n";
    echo "--- FIX COMPLETE! ---\n";

} catch (\Exception $e) {
    echo "✘ ERROR: " . $e->getMessage() . "\n";
}
