<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shortages = \App\Models\WaiterDailyReconciliation::where('difference', '<', 0)->get(['id', 'waiter_id', 'reconciliation_date', 'difference', 'status', 'reconciliation_type'])->toArray();
echo json_encode($shortages, JSON_PRETTY_PRINT);
