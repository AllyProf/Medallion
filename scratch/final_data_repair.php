<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FinancialHandover;
use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;
use App\Models\BarShift;

$ownerId = 4; // Based on metadata query

echo "Starting Comprehensive Data Repair...\n";

// 1. RELOCATE SHIFTS
echo "Relocating S000002 to April 15 (as it was closed yesterday) and S000003 to April 16...\n";
// Actually, user said: "ID 02 set to yesterday (16) and ID 03 for today (17)"
// I will follow the user's LATEST instruction: ID 2 -> Apr 16, ID 3 -> Apr 17.

// Shift 2 (S000002) -> 2026-04-16
FinancialHandover::where('bar_shift_id', 2)->update(['handover_date' => '2026-04-16']);
WaiterDailyReconciliation::where('bar_shift_id', 2)->update(['reconciliation_date' => '2026-04-16']);
BarShift::where('id', 2)->update(['opened_at' => '2026-04-16 08:00:00', 'closed_at' => '2026-04-16 23:59:59']);

// Shift 3 (S000003) -> 2026-04-17
FinancialHandover::where('bar_shift_id', 3)->update(['handover_date' => '2026-04-17']);
WaiterDailyReconciliation::where('bar_shift_id', 3)->update(['reconciliation_date' => '2026-04-17']);
BarShift::where('id', 3)->update(['opened_at' => '2026-04-17 08:00:00', 'closed_at' => '2026-04-17 23:59:59']);

// 2. REPAIR LEDGERS
echo "Updating Ledger fields to match shift data...\n";

// April 16 (Shift S000002)
$ledger16 = DailyCashLedger::updateOrCreate(
    ['user_id' => $ownerId, 'ledger_date' => '2026-04-16'],
    [
        'total_cash_received' => 130500,
        'total_digital_received' => 246000,
        'profit_generated' => 133986,
        'status' => 'closed'
    ]
);
$ledger16->syncTotals();

// April 17 (Shift S000003)
$ledger17 = DailyCashLedger::updateOrCreate(
    ['user_id' => $ownerId, 'ledger_date' => '2026-04-17'],
    [
        'total_cash_received' => 136000,
        'total_digital_received' => 0,
        'profit_generated' => 52045,
        'status' => 'open',
        'opening_cash' => $ledger16->expected_closing_cash
    ]
);
$ledger17->syncTotals();

echo "Repair Complete. Please refresh history.\n";
