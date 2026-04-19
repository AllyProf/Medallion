<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shiftId = 4;
$shift = \App\Models\BarShift::find($shiftId);
if($shift) {
    echo "SHIFT 4:\n";
    echo "  opened_at: " . $shift->opened_at . "\n";
    echo "  closed_at: " . $shift->closed_at . "\n";
}

$handover = \App\Models\FinancialHandover::where('bar_shift_id', $shiftId)->first();
if($handover) {
    echo "HANDOVER FOR 4:\n";
    echo "  handover_date: " . $handover->handover_date . "\n";
}

$ledgerFor17 = \App\Models\DailyCashLedger::whereDate('ledger_date', '2026-04-17')->first();
if($ledgerFor17) {
    echo "LEDGER 2026-04-17:\n";
    echo "  opening: " . $ledgerFor17->opening_cash . "\n";
    echo "  collections: " . ($ledgerFor17->total_cash_received + $ledgerFor17->total_digital_received) . "\n";
}

$ledgerFor18 = \App\Models\DailyCashLedger::whereDate('ledger_date', '2026-04-18')->first();
if($ledgerFor18) {
    echo "LEDGER 2026-04-18:\n";
    echo "  opening: " . $ledgerFor18->opening_cash . "\n";
    echo "  collections: " . ($ledgerFor18->total_cash_received + $ledgerFor18->total_digital_received) . "\n";
}
