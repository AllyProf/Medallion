<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- DEEP ANALYSIS OF SHIFTS, ORDERS AND HANDOVERS (MAY 20 - MAY 23) ---\n\n";

$shifts = \App\Models\BarShift::with(['staff'])
    ->where('created_at', '>=', '2026-05-20 00:00:00')
    ->orderBy('created_at', 'asc')
    ->get();

foreach ($shifts as $shift) {
    echo "=================================================\n";
    echo "SHIFT ID: " . $shift->id . " | Status: " . $shift->status . "\n";
    echo "Opened At: " . $shift->opened_at->format('Y-m-d H:i:s') . " by " . ($shift->staff->full_name ?? 'Unknown') . "\n";
    if ($shift->closed_at) {
        echo "Closed At: " . $shift->closed_at->format('Y-m-d H:i:s') . "\n";
    } else {
        echo "Closed At: NOT YET CLOSED\n";
    }
    
    // Orders
    $ordersCount = \App\Models\BarOrder::where('bar_shift_id', $shift->id)->count();
    $totalSales = \App\Models\BarOrder::where('bar_shift_id', $shift->id)->sum('total_amount');
    echo "Total Orders: " . $ordersCount . " | Total Sales Value: TSh " . number_format($totalSales) . "\n";
    
    // Handovers associated with this shift
    $handovers = \App\Models\FinancialHandover::where('bar_shift_id', $shift->id)->get();
    echo "Handovers Attached to this Shift:\n";
    if ($handovers->isEmpty()) {
        echo "   (No handovers found)\n";
    } else {
        foreach ($handovers as $h) {
            echo "   - Handover ID " . $h->id . " | Amount: TSh " . number_format($h->amount) . " | Date Assigned: " . $h->handover_date->format('Y-m-d') . "\n";
        }
    }
    echo "=================================================\n\n";
}

echo "--- DAILY LEDGER DATABASE RECORDS ---\n\n";
$ledgers = \App\Models\DailyCashLedger::where('ledger_date', '>=', '2026-05-20')
    ->orderBy('ledger_date', 'asc')
    ->get();
foreach ($ledgers as $l) {
    echo "Ledger Date: " . $l->ledger_date->format('Y-m-d') . " | Status: " . $l->status . "\n";
    echo "   Opening Cash: " . number_format($l->opening_cash) . "\n";
    echo "   Total Cash Received: " . number_format($l->total_cash_received) . "\n";
    echo "   Carried Forward (Rollover): " . number_format($l->carried_forward) . "\n";
    echo "-------------------------------------------------\n";
}
