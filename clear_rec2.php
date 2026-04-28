<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$counterRoleIds = App\Models\Role::whereIn('slug', ['counter', 'counter-staff', 'bar-manager'])->pluck('id');
$counterUsers = App\Models\User::whereIn('role_id', $counterRoleIds)->pluck('id');

$deleted = App\Models\WaiterDailyReconciliation::whereIn('waiter_id', $counterUsers)->where('status', 'submitted')->delete();

echo 'Deleted ' . $deleted . ' auto-generated records for counter staff.';
