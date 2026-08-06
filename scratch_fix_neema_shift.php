<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== BEFORE FIX: Current State ===\n";

// Shift #51 - Neema's 1-minute empty shift
$shift51 = \App\Models\BarShift::find(51);
echo "Shift #51: Status=" . $shift51->status . " | Opened=" . $shift51->opened_at . " | Closed=" . $shift51->closed_at . "\n";

// Its handover
$h67 = \App\Models\FinancialHandover::find(67);
echo "Handover #67: Amount=" . number_format($h67->amount) . " | Date=" . $h67->handover_date . " | Status=" . $h67->status . "\n";

// Ledger May 23
$ledger23 = \App\Models\DailyCashLedger::where('ledger_date', '2026-05-23')->first();
echo "Ledger May 23: Opening=" . number_format($ledger23->opening_cash) . " | CashReceived=" . number_format($ledger23->total_cash_received) . " | Rollover=" . number_format($ledger23->carried_forward) . "\n\n";

echo "=== STEP 1: Voiding Handover #67 (Neema's empty 12,000 TSh) ===\n";
$h67->delete();
echo "Handover #67 deleted.\n\n";

echo "=== STEP 2: Remove Shift #51 (the 1-minute ghost shift) ===\n";
$shift51->delete();
echo "Shift #51 deleted.\n\n";

echo "=== STEP 3: Force recascade all ledgers from May 20 onwards ===\n";
$ledgers = \App\Models\DailyCashLedger::where('ledger_date', '>=', '2026-05-20')
    ->orderBy('ledger_date', 'asc')
    ->get();

foreach ($ledgers as $ledger) {
    $prevLedger = \App\Models\DailyCashLedger::where('user_id', $ledger->user_id)
        ->where('ledger_date', '<', $ledger->ledger_date)
        ->orderBy('ledger_date', 'desc')
        ->first();
        
    $ledger->opening_cash = $prevLedger ? $prevLedger->carried_forward : 0;
    $ledger->syncTotals();
    $ledger->save();
    
    echo "Ledger " . $ledger->ledger_date->format('Y-m-d') 
        . " | Opening=" . number_format($ledger->opening_cash) 
        . " | Cash Received=" . number_format($ledger->total_cash_received) 
        . " | Rollover=" . number_format($ledger->carried_forward) . "\n";
}

echo "\n=== DONE: System is now accurate ===\n";
