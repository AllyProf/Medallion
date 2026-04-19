<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- LEDGER USER IDs --- \n";
$ledgers = DB::table('daily_cash_ledgers')->select('user_id', 'ledger_date', 'total_cash_received')->get();
foreach($ledgers as $l) {
    echo "USER: {$l->user_id} | DATE: {$l->ledger_date} | CASH: {$l->total_cash_received}\n";
}
