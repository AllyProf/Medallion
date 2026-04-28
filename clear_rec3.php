<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$deleted = App\Models\WaiterDailyReconciliation::whereDate('created_at', date('Y-m-d'))->delete();

echo 'Deleted ' . $deleted . ' records from today.';
