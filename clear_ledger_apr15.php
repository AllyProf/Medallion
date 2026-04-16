<?php
// clear_ledger_apr15.php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;

// Targeting the specific ledger for April 15
$ledger = DailyCashLedger::where('ledger_date', '2026-04-15')->first();

if ($ledger) {
    echo "Found ledger for April 15. Current Profit Generated: " . $ledger->profit_generated . "\n";
    $ledger->update(['profit_generated' => 0]);
    echo "Successfully cleared profit to 0.\n";
} else {
    echo "Ledger for April 15 not found.\n";
}
