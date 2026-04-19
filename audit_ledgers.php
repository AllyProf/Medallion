<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;
use App\Models\BarShift;

echo "TIME: " . now() . "\n";
echo "--------------------------------------------------\n";

for($i=15; $i<=19; $i++) {
    $date = "2026-04-" . str_pad($i, 2, "0", STR_PAD_LEFT);
    $l = DailyCashLedger::whereDate('ledger_date', $date)->first();
    $shifts = BarShift::whereDate('opened_at', $date)->pluck('id')->toArray();
    
    echo "DATE: $date\n";
    echo "  Shifts: " . implode(", ", $shifts) . "\n";
    if($l) {
        echo "  Opening: " . $l->opening_cash . "\n";
        echo "  Cash Received: " . $l->total_cash_received . "\n";
        echo "  Digital Received: " . $l->total_digital_received . "\n";
        echo "  Profit Generated: " . $l->profit_generated . "\n";
        echo "  Carried Forward: " . $l->carried_forward . "\n";
    } else {
        echo "  LEDGER NOT FOUND\n";
    }
    echo "--------------------------------------------------\n";
}
