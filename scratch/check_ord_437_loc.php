<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;

$orderNumber = 'ORD-437';
$o = BarOrder::where('order_number', $orderNumber)->with('table')->first();

echo "ORDER: $orderNumber\n";
echo "Table Number: " . ($o->table->table_number ?? 'NONE') . "\n";
echo "Location: " . ($o->table->location ?? 'NONE') . "\n";
