<?php
use App\Models\BarShift;
use App\Models\FinancialHandover;
use App\Models\DailyCashLedger;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Absolute Final History Fix...\n";

// 1. Shift 2 -> April 15
$s2 = BarShift::find(2);
if ($s2) {
    echo "Moving Shift 2 to April 15...\n";
    $s2->opened_at = '2026-04-15 08:00:00';
    $s2->closed_at = '2026-04-16 02:00:00';
    $s2->save();
    FinancialHandover::where('bar_shift_id', 2)->update(['handover_date' => '2026-04-15']);
}

// 2. Shift 3 -> April 16
$s3 = BarShift::find(3);
if ($s3) {
    echo "Moving Shift 3 to April 16...\n";
    $s3->opened_at = '2026-04-16 08:00:00';
    $s3->closed_at = '2026-04-17 02:00:00';
    $s3->save();
    FinancialHandover::where('bar_shift_id', 3)->update(['handover_date' => '2026-04-16']);
}

// 3. Recovery Pay (152,000) -> April 17
// We search for a handover of exactly 152,000 that was created on the 19th
$recovery = FinancialHandover::where('amount', 152000)
    ->whereDate('handover_date', '2026-04-19')
    ->first();
if ($recovery) {
    echo "Moving Recovery Pay (152,000) to April 17...\n";
    $recovery->handover_date = '2026-04-17';
    $recovery->bar_shift_id = null; // Decouple from shift if any
    $recovery->save();
} else {
    // If not found on 19th, check if it was already moved
    echo "Recovery Pay (152,000) check: " . (FinancialHandover::where('amount', 152000)->count() > 0 ? 'Exists somewhere' : 'Missing') . "\n";
}

// 4. Shift 4 -> April 18 (PENDING)
$s4 = BarShift::find(4);
if ($s4) {
    echo "Moving Shift 4 to April 18 and setting to PENDING...\n";
    $s4->opened_at = '2026-04-18 08:00:00';
    $s4->closed_at = '2026-04-19 02:00:00';
    $s4->save();
    
    // Set handover to PENDING
    FinancialHandover::where('bar_shift_id', 4)->update([
        'handover_date' => '2026-04-18',
        'status' => 'pending'
    ]);
}

// 5. Fix Ledger States
echo "Updating Ledger states and syncing...\n";
// Ensure 18th is 'open'
DailyCashLedger::where('ledger_date', '2026-04-18')->update(['status' => 'open']);
// Ensure 15, 16, 17 are 'closed' (if they have data)
DailyCashLedger::whereIn('ledger_date', ['2026-04-15', '2026-04-16', '2026-04-17'])->update(['status' => 'closed']);

echo "Done! Please run the master repair script now.\n";
