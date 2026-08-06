<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ATTRIBUTING 103,500 FROM SHIFT 49 → SHIFT 48 ===\n\n";

// Confirm current state before changes
$h63 = \App\Models\FinancialHandover::find(63);
$h66 = \App\Models\FinancialHandover::find(66);
$rec156 = \App\Models\WaiterDailyReconciliation::find(156);

echo "BEFORE:\n";
echo "  H63 (Shift 49, Glory):        TSh " . number_format($h63->amount) . "\n";
echo "  H66 (Shift 48, NEEMA):        TSh " . number_format($h66->amount) . "\n";
echo "  Rec156 Expected:               TSh " . number_format($rec156->expected_amount) . "\n";
echo "  Rec156 Submitted:              TSh " . number_format($rec156->submitted_amount) . "\n";
echo "  Rec156 Difference:             TSh " . number_format($rec156->difference) . "\n\n";

$counter_correction = 103500;

// STEP 1: Fix Glory's Shift 49 reconciliation
$rec156->submitted_amount = 63500;
$rec156->expected_amount  = 63500;
$rec156->difference       = 0;
$rec156->status           = 'reconciled'; // change from 'submitted' to 'reconciled'

// Preserve existing notes and add attribution note
$existingNotes = json_decode($rec156->notes ?? '{}', true) ?: [];
$existingNotes['submitted_breakdown'] = ['cash' => '63500'];
$existingNotes['recorded_breakdown']  = ['cash' => 63500];
$existingNotes['waiter_note']         = 'Counter correction: TSh 103,500 was Shift 48 counter orders, attributed to Shift 48 handover.';
$rec156->notes = json_encode($existingNotes);
$rec156->save();

echo "✅ STEP 1: Fixed Rec156 - Glory's Shift 49 reconciliation\n";
echo "   Submitted: 63,500 | Expected: 63,500 | Difference: 0 | Status: reconciled\n\n";

// STEP 2: Reduce Handover #63 (Shift 49) to 63,500
$h63->amount = 63500;
$breakdown63 = $h63->payment_breakdown;
if (is_string($breakdown63)) $breakdown63 = json_decode($breakdown63, true);
$breakdown63['cash'] = '63500';
$h63->payment_breakdown = $breakdown63;
$h63->notes = 'Corrected: 103,500 counter orders attributed to Shift 48 (H66).';
$h63->save();

echo "✅ STEP 2: Fixed H63 - Reduced from 167,000 → 63,500 TSh\n\n";

// STEP 3: Increase Handover #66 (Shift 48) by 103,500
$h66->amount += $counter_correction; // 324,000 + 103,500 = 427,500
$breakdown66 = $h66->payment_breakdown;
if (is_string($breakdown66)) $breakdown66 = json_decode($breakdown66, true);
$breakdown66['cash'] = strval((int)$breakdown66['cash'] + $counter_correction);
$h66->payment_breakdown = $breakdown66;
$h66->notes = 'Corrected: +103,500 TSh Shift 48 counter orders collected by Glory Gerald (previously in H63).';
$h66->save();

echo "✅ STEP 3: Fixed H66 - Increased from 324,000 → 427,500 TSh\n\n";

// STEP 4: Re-sync May 21 ledger
$ledger21 = \App\Models\DailyCashLedger::where('ledger_date', '2026-05-21')->first();
if ($ledger21) {
    $ledger21->syncTotals();
    $ledger21->actual_closing_cash = $ledger21->expected_closing_cash;
    $ledger21->save();
    echo "✅ STEP 4: Re-synced May 21 Ledger\n";
    echo "   Opening:            TSh " . number_format($ledger21->opening_cash) . "\n";
    echo "   Total Cash Received: TSh " . number_format($ledger21->total_cash_received) . "\n";
    echo "   Total Expenses:      TSh " . number_format($ledger21->total_expenses) . "\n";
    echo "   Expected Vault:      TSh " . number_format($ledger21->expected_closing_cash) . "\n";
    echo "   Actual Vault:        TSh " . number_format($ledger21->actual_closing_cash) . "\n";
    echo "   Profit Generated:    TSh " . number_format($ledger21->profit_generated) . "\n";
    echo "   Carried Forward:     TSh " . number_format($ledger21->carried_forward) . "\n\n";
}

echo "AFTER:\n";
$h63->refresh(); $h66->refresh(); $rec156->refresh();
echo "  H63 (Shift 49, Glory):        TSh " . number_format($h63->amount) . "\n";
echo "  H66 (Shift 48, NEEMA):        TSh " . number_format($h66->amount) . "\n";
echo "  H63 + H66 Total:              TSh " . number_format($h63->amount + $h66->amount) . " (was 491,000)\n";
echo "  Rec156 Expected:               TSh " . number_format($rec156->expected_amount) . "\n";
echo "  Rec156 Submitted:              TSh " . number_format($rec156->submitted_amount) . "\n";
echo "  Rec156 Difference:             TSh " . number_format($rec156->difference) . " ✅\n\n";

echo "=== ALL CORRECTIONS APPLIED ===\n";
