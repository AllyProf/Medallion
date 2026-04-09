<?php
$ledger = \App\Models\DailyCashLedger::whereDate('ledger_date', date('Y-m-d'))->where('status', 'open')->first();
if ($ledger) {
    $ledger->total_cash_received = 22000;
    $ledger->total_digital_received = 10000;
    $ledger->expected_closing_cash = $ledger->opening_cash + $ledger->total_cash_received - $ledger->expenses()->sum('amount');
    $ledger->save();
    echo "Ledger fixed successfully!\n";
} else {
    echo "No open ledger found for today.\n";
}
