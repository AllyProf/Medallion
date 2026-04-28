<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$waiter = App\Models\User::where('email', 'justineneema957@gmail.com')->first();
if ($waiter) {
    App\Models\WaiterDailyReconciliation::where('waiter_id', $waiter->id)->where('status', 'submitted')->delete();
    echo 'Deleted successfully';
} else {
    echo 'Waiter not found';
}
