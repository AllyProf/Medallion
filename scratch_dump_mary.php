<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rec = \App\Models\WaiterDailyReconciliation::find(4);
var_dump($rec->toArray());
