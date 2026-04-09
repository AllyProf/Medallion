<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$table = 'waiter_daily_reconciliations';
$indexes = DB::select("SHOW INDEX FROM `{$table}`");
echo json_encode($indexes, JSON_PRETTY_PRINT);
