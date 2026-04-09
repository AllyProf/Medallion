<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2026-04-08';
$waiter_id = 15;

$records = DB::table('waiter_daily_reconciliations')
    ->where('waiter_id', $waiter_id)
    ->where('reconciliation_date', $date)
    ->get();

foreach ($records as $r) {
    echo "ID: {$r->id}, Type: {$r->reconciliation_type}, Shift: {$r->bar_shift_id}, Expected: {$r->expected_amount}, Submitted: {$r->submitted_amount}, Status: {$r->status}\n";
}
