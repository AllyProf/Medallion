<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$shifts = DB::table('bar_shifts')->get();
echo "SHIFTS:\n";
foreach($shifts as $s) {
    echo "  ID: {$s->id} | opened_at: {$s->opened_at}\n";
}

$ledgers = DB::table('daily_cash_ledgers')->get();
echo "\nLEDGERS:\n";
foreach($ledgers as $l) {
    echo "  DATE: {$l->ledger_date} | COLLECTED: " . ($l->total_cash_received + $l->total_digital_received) . " | PROFIT: {$l->profit_generated}\n";
}
