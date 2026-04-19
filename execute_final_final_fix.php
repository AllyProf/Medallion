<?php
use App\Models\BarShift;
use App\Models\FinancialHandover;
use App\Models\DailyCashLedger;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting True Final Alignment...\n";

// 1. Shift 4 -> April 17
$s4 = BarShift::find(4);
if ($s4) {
    echo "Moving Shift 4 to April 17...\n";
    $s4->opened_at = '2026-04-17 08:00:00';
    $s4->closed_at = '2026-04-18 02:00:00';
    $s4->save();
    
    FinancialHandover::where('bar_shift_id', 4)->update([
        'handover_date' => '2026-04-17'
    ]);
}

// 2. Shift 5 -> April 18
$s5 = BarShift::find(5);
if ($s5) {
    echo "Moving Shift 5 to April 18...\n";
    $s5->opened_at = '2026-04-18 08:00:00';
    $s5->closed_at = '2026-04-19 02:00:00';
    $s5->save();
    
    FinancialHandover::where('bar_shift_id', 5)->update([
        'handover_date' => '2026-04-18'
    ]);
}

// Ensure 17 and 18 are open/closed correctly
DailyCashLedger::where('ledger_date', '2026-04-17')->update(['status' => 'closed']);
DailyCashLedger::where('ledger_date', '2026-04-18')->update(['status' => 'open']);
DailyCashLedger::where('ledger_date', '2026-04-19')->update(['status' => 'open']);

echo "Done! Please run database_master_repair.php to sync.\n";
