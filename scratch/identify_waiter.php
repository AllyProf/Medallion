<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;

$orderNumber = 'ORD-438';
$o = BarOrder::where('order_number', $orderNumber)->with('waiter')->first();

echo "ORDER: $orderNumber\n";
echo "Created At: " . $o->created_at . "\n";
echo "Waiter ID: " . $o->waiter_id . "\n";
echo "Waiter Name: " . ($o->waiter->full_name ?? 'N/A') . "\n";
echo "Amount: " . $o->total_amount . "\n";
