<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- FORCING LEDGER RECALCULATION & CASCADE ---\n";

$ledgers = \App\Models\DailyCashLedger::orderBy('ledger_date', 'asc')->get();

foreach ($ledgers as $ledger) {
    // 1. Force recalculate previous closing cash
    $prevLedger = \App\Models\DailyCashLedger::where('user_id', $ledger->user_id)
        ->where('ledger_date', '<', $ledger->ledger_date)
        ->orderBy('ledger_date', 'desc')
        ->first();
        
    $openingCash = $prevLedger ? $prevLedger->carried_forward : 0;
    
    // 2. Set Opening Cash
    $ledger->opening_cash = $openingCash;
    
    // 3. Force Sync and Save (this calculates carried_forward and persists)
    $ledger->syncTotals();
    $ledger->save();
    
    echo "Ledger " . $ledger->ledger_date->format('Y-m-d') . " | Opening: " . number_format($ledger->opening_cash) . " | Rollover: " . number_format($ledger->carried_forward) . "\n";
}

echo "Cascade complete!\n";
