<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$ledgers = DB::table('daily_cash_ledgers')->get();
echo "--- ALL LEDGERS ---\n";
foreach($ledgers as $l) {
    echo "USER: {$l->user_id} | DATE: {$l->ledger_date} | CASH: {$l->total_cash_received} | DIGITAL: {$l->total_digital_received}\n";
}

$handovers = DB::table('financial_handovers')->get();
echo "\n--- ALL HANDOVERS ---\n";
foreach($handovers as $h) {
    echo "USER: {$h->user_id} | SHIFT: {$h->bar_shift_id} | DATE: {$h->handover_date} | STATUS: {$h->status}\n";
}
