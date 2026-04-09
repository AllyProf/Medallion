<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$table = 'waiter_daily_reconciliations';
$indexes = DB::select("SHOW INDEX FROM `{$table}`");
file_put_contents('c:/xampp/htdocs/Mauzo_Link_V2/indexes_output.json', json_encode($indexes, JSON_PRETTY_PRINT));
