<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rec = \App\Models\WaiterDailyReconciliation::find(4);
if ($rec) {
    $rec->expected_amount = 1000;
    $rec->submitted_amount = 0; // ensure
    $rec->difference = -1000;
    $rec->notes = "Shortage recorded via Chef Handover attribution. Amount: TSh 1,000";
    $rec->save();
    echo "Successfully forcefully updated Mary's record to expected=1000, difference=-1000.\n";
}
