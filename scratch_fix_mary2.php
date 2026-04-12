<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rec = \App\Models\WaiterDailyReconciliation::find(4);
if ($rec) {
    if (strpos($rec->notes, '1,000') !== false) {
        $rec->expected_amount = 1000;
        $rec->submitted_amount = 0; // ensure
        $rec->difference = -1000;
        $rec->save();
        echo "Successfully updated Mary's record to expected=1000, difference=-1000.\n";
    }
}
