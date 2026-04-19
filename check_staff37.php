<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$staff = \App\Models\Staff::with('role')->find(37);
echo "STAFF_DATA:" . json_encode($staff) . "\n";
