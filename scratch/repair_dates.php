<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FinancialHandover;
use App\Models\WaiterDailyReconciliation;
use App\Models\DailyCashLedger;

$ownerId = 4; // Based on metadata query

echo "Starting Data Repair...\n";

// 1. Shift S000002 (ID 2) -> April 16 (Yesterday)
echo "Relocating Shift S000002 (ID 2) to 2026-04-16...\n";
FinancialHandover::where('bar_shift_id', 2)->update(['handover_date' => '2026-04-16']);
WaiterDailyReconciliation::where('bar_shift_id', 2)->update(['reconciliation_date' => '2026-04-16']);

// 2. Shift S000003 (ID 3) -> April 17 (Today)
echo "Relocating Shift S000003 (ID 3) to 2026-04-17...\n";
FinancialHandover::where('bar_shift_id', 3)->update(['handover_date' => '2026-04-17']);
WaiterDailyReconciliation::where('bar_shift_id', 3)->update(['reconciliation_date' => '2026-04-17']);

// 3. Ensure Ledgers exist and are ready for sync
DailyCashLedger::firstOrCreate(['user_id' => $ownerId, 'ledger_date' => '2026-04-16'], ['status' => 'closed']);
DailyCashLedger::firstOrCreate(['user_id' => $ownerId, 'ledger_date' => '2026-04-17'], ['status' => 'open']);

echo "Repair Complete. Please refresh history.\n";
