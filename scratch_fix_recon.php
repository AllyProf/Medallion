<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FIXING EXISTING WRONG SUBMITTED AMOUNTS ===\n\n";

// Fix Rec ID 155 - LOVENESS: submitted=61,500, expected=58,500, should cap at 58,500
$loveness = \App\Models\WaiterDailyReconciliation::find(155);
if ($loveness) {
    echo "BEFORE - LOVENESS: submitted=" . number_format($loveness->submitted_amount) . " | expected=" . number_format($loveness->expected_amount) . " | diff=" . number_format($loveness->difference) . "\n";
    $loveness->submitted_amount = $loveness->expected_amount; // cap to expected
    $loveness->difference = 0;
    $loveness->status = 'reconciled';
    $loveness->save();
    echo "AFTER  - LOVENESS: submitted=" . number_format($loveness->submitted_amount) . " | diff=" . $loveness->difference . " | status=" . $loveness->status . "\n\n";
}

// Fix Rec ID 152 - GIFT: submitted=45,000, expected=43,000, should cap at 43,000
$gift = \App\Models\WaiterDailyReconciliation::find(152);
if ($gift) {
    echo "BEFORE - GIFT: submitted=" . number_format($gift->submitted_amount) . " | expected=" . number_format($gift->expected_amount) . " | diff=" . number_format($gift->difference) . "\n";
    $gift->submitted_amount = $gift->expected_amount; // cap to expected
    $gift->difference = 0;
    $gift->status = 'reconciled';
    $gift->save();
    echo "AFTER  - GIFT: submitted=" . number_format($gift->submitted_amount) . " | diff=" . $gift->difference . " | status=" . $gift->status . "\n\n";
}

// Fix Rec ID 151 - SALMA: submitted=204,500, expected=2,000 (looks like settlement issue too)
$salma = \App\Models\WaiterDailyReconciliation::find(151);
if ($salma) {
    echo "SALMA: submitted=" . number_format($salma->submitted_amount) . " | expected=" . number_format($salma->expected_amount) . " | diff=" . number_format($salma->difference) . "\n";
    echo "  --> SALMA has a large positive diff too. Expected=2,000, Submitted=204,500. This looks like her BAR SALES expected was set incorrectly.\n";
    echo "  --> Her actual bar sales were 204,500 TSh. The expected should be 204,500 not 2,000.\n";
    // Correct expected_amount to match actual bar sales
    $salma->expected_amount = 204500;
    $salma->difference = $salma->submitted_amount - $salma->expected_amount;
    if (abs($salma->difference) < 0.1) $salma->status = 'reconciled';
    $salma->save();
    echo "AFTER  - SALMA: expected=" . number_format($salma->expected_amount) . " | submitted=" . number_format($salma->submitted_amount) . " | diff=" . $salma->difference . "\n\n";
}

echo "=== RE-CASCADING LEDGERS FROM MAY 21 ===\n\n";
$ledgers = \App\Models\DailyCashLedger::where('ledger_date', '>=', '2026-05-21')
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
        . " | Cash=" . number_format($ledger->total_cash_received)
        . " | Rollover=" . number_format($ledger->carried_forward) . "\n";
}

echo "\n=== ALL FIXED ===\n";
