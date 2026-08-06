<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarOrder;
use App\Models\BarShift;
use App\Models\DailyCashLedger;
use App\Models\FinancialHandover;
use App\Models\WaiterDailyReconciliation;

$date = '2026-06-06';
$ledger = DailyCashLedger::whereDate('ledger_date', $date)->first();
if (!$ledger) {
    echo "No ledger for {$date}\n";
    exit(1);
}

$ledger->syncTotals();

echo "=== Ledger {$date} (status: {$ledger->status}) ===\n";
echo "opening_cash: {$ledger->opening_cash}\n";
echo "total_cash_received: {$ledger->total_cash_received}\n";
echo "total_digital_received: {$ledger->total_digital_received}\n";
echo "collections total: " . ($ledger->total_cash_received + $ledger->total_digital_received) . "\n";
echo "total_expenses: {$ledger->total_expenses}\n";
echo "profit_generated: {$ledger->profit_generated}\n";
echo "money_in_circulation: {$ledger->money_in_circulation}\n";
echo "expectedRevenue: {$ledger->expectedRevenue}\n";
echo "grossProfit: {$ledger->grossProfit}\n";
echo "totalDayShortage: {$ledger->totalDayShortage}\n";
$margin = $ledger->expectedRevenue > 0 ? ($ledger->grossProfit / $ledger->expectedRevenue) : 0;
echo "profit margin %: " . round($margin * 100, 2) . "\n";
echo "profit check (collections * margin): " . round(($ledger->total_cash_received + $ledger->total_digital_received) * $margin) . "\n";

$shiftIds = BarShift::where('user_id', $ledger->user_id)->whereDate('opened_at', $date)->pluck('id')->toArray();
echo "\nShifts on {$date}: " . implode(',', $shiftIds) . "\n";

$handovers = FinancialHandover::whereIn('bar_shift_id', $shiftIds ?: [0])
    ->orWhere(fn ($q) => $q->whereNull('bar_shift_id')->whereDate('handover_date', $date))
    ->get();
echo "Handovers: {$handovers->count()}\n";
foreach ($handovers as $h) {
    echo "  H#{$h->id} shift={$h->bar_shift_id} status={$h->status} amount={$h->amount}\n";
}

$recs = WaiterDailyReconciliation::where('user_id', $ledger->user_id)
    ->whereIn('bar_shift_id', $shiftIds ?: [0])
    ->where('reconciliation_type', 'bar')
    ->get();
echo "\nReconciliations:\n";
foreach ($recs as $r) {
    $diff = (float) $r->submitted_amount - (float) $r->expected_amount;
    echo "  #{$r->id} waiter={$r->waiter_id} exp={$r->expected_amount} sub={$r->submitted_amount} diff={$diff} status={$r->status}\n";
}

$orders = BarOrder::whereIn('bar_shift_id', $shiftIds ?: [0])->whereIn('status', ['served', 'delivered'])->count();
echo "\nServed orders count: {$orders}\n";
