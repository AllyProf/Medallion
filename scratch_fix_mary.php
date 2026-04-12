<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rec = \App\Models\WaiterDailyReconciliation::find(4);
if ($rec && $rec->reconciliation_type === 'food') {
    // If the difference is currently 0, but there's a note about 1000 TSh
    if (strpos($rec->notes, '1,000') !== false) {
        $rec->difference = -1000;
        $rec->save();
        echo "Successfully updated Mary's record to -1000.\n";
    }
}
