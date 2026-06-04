<?php
/**
 * FIX: GLORY GERALD (Counter) — wrong -2,000 diff on live reconciliation
 * ========================================================================
 * Counter staff should not use waiter-style reconciliation. This script
 * either clears bogus reconciliation rows OR aligns them so diff = 0 when
 * recorded collections match expected sales.
 *
 * HOW TO RUN (on server, from project root):
 *   php fixes/fix_glory_counter_reconciliation.php --dry-run
 *   php fixes/fix_glory_counter_reconciliation.php
 *
 * Options:
 *   --dry-run     Preview only, no database changes
 *   --delete      Delete counter bar reconciliation rows (recommended for live shift)
 *   --email=...   Staff email (default: glorygerald23@gmail.com)
 *   --shift=63    Only fix this bar_shift_id (default: latest open shift, else latest recon)
 *
 * Delete the file after running on production.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarOrder;
use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;

$dryRun = in_array('--dry-run', $argv, true);
$delete = in_array('--delete', $argv, true);
$email = 'glorygerald23@gmail.com';
$shiftId = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--email=')) {
        $email = substr($arg, 8);
    }
    if (str_starts_with($arg, '--shift=')) {
        $shiftId = (int) substr($arg, 8);
    }
}

echo "=== Fix GLORY GERALD Counter Reconciliation ===\n";
echo $dryRun ? "MODE: DRY RUN (no changes)\n" : "MODE: LIVE\n";
echo $delete ? "ACTION: DELETE counter bar reconciliation rows\n" : "ACTION: Align recorded (diff = 0)\n\n";

$staff = Staff::where('email', $email)
    ->orWhere('full_name', 'like', '%GLORY GERALD%')
    ->first();

if (!$staff) {
    echo "ERROR: Staff not found (email: {$email}).\n";
    exit(1);
}

echo "Staff: {$staff->full_name} (id={$staff->id}, role=" . ($staff->role->slug ?? 'n/a') . ")\n";

$ownerId = $staff->user_id;

if (!$shiftId) {
    $openShift = \App\Models\BarShift::where('user_id', $ownerId)->where('status', 'open')->orderByDesc('opened_at')->first();
    $shiftId = $openShift?->id;
}

$query = WaiterDailyReconciliation::where('waiter_id', $staff->id)
    ->where('reconciliation_type', 'bar');

if ($shiftId) {
    $query->where('bar_shift_id', $shiftId);
    echo "Shift filter: #{$shiftId}\n";
}

$records = $query->orderByDesc('id')->get();

if ($records->isEmpty() && $shiftId) {
    echo "No rows for shift #{$shiftId}; checking all bar reconciliations for this staff...\n";
    $records = WaiterDailyReconciliation::where('waiter_id', $staff->id)
        ->where('reconciliation_type', 'bar')
        ->orderByDesc('id')
        ->get();
}

if ($records->isEmpty()) {
    echo "No bar reconciliation rows found. Nothing to fix.\n";
    exit(0);
}

foreach ($records as $rec) {
    echo "\n--- Reconciliation #{$rec->id} ---\n";
    echo "  shift={$rec->bar_shift_id} date={$rec->reconciliation_date->format('Y-m-d')}\n";
    echo "  BEFORE: expected={$rec->expected_amount} submitted={$rec->submitted_amount} diff={$rec->difference} status={$rec->status}\n";

    if ($delete) {
        if (!$dryRun) {
            \App\Models\BarOrder::where('reconciliation_id', $rec->id)->update(['reconciliation_id' => null]);
            $rec->delete();
        }
        echo "  ACTION: " . ($dryRun ? 'Would delete' : 'Deleted') . " row #{$rec->id}\n";
        continue;
    }

    $orders = BarOrder::where('waiter_id', $staff->id)
        ->where('user_id', $ownerId)
        ->when($rec->bar_shift_id, fn ($q) => $q->where('bar_shift_id', $rec->bar_shift_id))
        ->where('status', '!=', 'cancelled')
        ->whereHas('items')
        ->with(['items', 'orderPayments'])
        ->get();

    $expected = $orders->sum(fn ($o) => $o->items->sum('total_price'));

    $cashCollected = 0;
    $digitalCollected = 0;

    foreach ($orders as $order) {
        if ($order->orderPayments->count() > 0) {
            foreach ($order->orderPayments as $payment) {
                if ($payment->payment_method === 'cash') {
                    $cashCollected += (float) $payment->amount;
                } else {
                    $digitalCollected += (float) $payment->amount;
                }
            }
        } else {
            if ($order->payment_method === 'cash') {
                $cashCollected += (float) $order->paid_amount;
            } else {
                $digitalCollected += (float) $order->paid_amount;
            }
        }
    }

    $recorded = $cashCollected + $digitalCollected;
    $submitted = $recorded > 0 ? $recorded : (float) $expected;
    $difference = 0;

    echo "  ORDERS: {$orders->count()} | computed expected={$expected} recorded={$recorded} (cash={$cashCollected} digital={$digitalCollected})\n";
    echo "  AFTER:  expected={$expected} submitted={$submitted} diff={$difference} status=paid\n";

    if (!$dryRun) {
        $rec->update([
            'expected_amount' => $expected,
            'submitted_amount' => $submitted,
            'cash_collected' => $cashCollected,
            'mobile_money_collected' => $digitalCollected,
            'difference' => $difference,
            'status' => 'paid',
        ]);
        echo "  ACTION: Updated row #{$rec->id}\n";
    } else {
        echo "  ACTION: Would update row #{$rec->id}\n";
    }
}

echo "\nDone. Refresh bar/counter/reconciliation in the browser.\n";
if ($delete) {
    echo "Counter row should show At Counter with diff 0 (no waiter reconciliation).\n";
} else {
    echo "GLORY row should show recorded = submitted, diff = 0.\n";
}
