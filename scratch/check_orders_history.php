<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;

$ownerId = 4;
$dates = ['2026-04-14', '2026-04-15', '2026-04-16'];

foreach ($dates as $date) {
    $count = BarOrder::where('user_id', $ownerId)
        ->whereDate('created_at', $date)
        ->where('status', 'served')
        ->count();
    $total = BarOrder::where('user_id', $ownerId)
        ->whereDate('created_at', $date)
        ->where('status', 'served')
        ->sum('total_amount');
    
    echo "Date: $date | Served Orders: $count | Total Value: $total\n";
}
