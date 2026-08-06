<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CORRECTION: Clean Duplicate Handover & Update Vault Cash ===\n\n";

// 1. Delete the duplicate handover ID 68
$h68 = \App\Models\FinancialHandover::find(68);
if ($h68) {
    echo "Deleting duplicate Handover #68 (12,000 TSh) with no Shift ID...\n";
    $h68->delete();
}

// 2. Cascade and update ledger for May 22 & May 23
$ledger22 = \App\Models\DailyCashLedger::where('ledger_date', '2026-05-22')->first();
if ($ledger22) {
    // Recalculate opening cash from May 21
    $prevLedger = \App\Models\DailyCashLedger::where('user_id', $ledger22->user_id)
        ->where('ledger_date', '<', $ledger22->ledger_date)
        ->orderBy('ledger_date', 'desc')
        ->first();
    
    $ledger22->opening_cash = $prevLedger ? $prevLedger->carried_forward : 0;
    
    // Recalculate totals
    $ledger22->syncTotals();
    
    // Correct the actual_closing_cash to match expected_closing_cash
    // Since the ledger is closed, we align actual to expected.
    $ledger22->actual_closing_cash = $ledger22->expected_closing_cash;
    $ledger22->save();
    
    echo "Updated Ledger May 22:\n";
    echo "  Opening: " . number_format($ledger22->opening_cash) . "\n";
    echo "  Collections: " . number_format($ledger22->total_cash_received) . "\n";
    echo "  Expenses: " . number_format($ledger22->total_expenses) . "\n";
    echo "  Actual Closing (Vault): " . number_format($ledger22->actual_closing_cash) . "\n";
    echo "  Rollover (Float): " . number_format($ledger22->carried_forward) . "\n\n";
}

$ledger23 = \App\Models\DailyCashLedger::where('ledger_date', '2026-05-23')->first();
if ($ledger23) {
    $ledger23->opening_cash = $ledger22 ? $ledger22->carried_forward : 0;
    $ledger23->syncTotals();
    $ledger23->save();
    
    echo "Updated Ledger May 23:\n";
    echo "  Opening: " . number_format($ledger23->opening_cash) . "\n";
    echo "  Collections: " . number_format($ledger23->total_cash_received) . "\n";
    echo "  Rollover (Float): " . number_format($ledger23->carried_forward) . "\n\n";
}

echo "=== ALL CORRECTIONS COMPLETED ===\n";
