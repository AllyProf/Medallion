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

echo json_encode($records, JSON_PRETTY_PRINT);
