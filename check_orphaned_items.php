<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$items = DB::table('order_items')->where('order_id', 167)->get();
echo "ITEMS_COUNT:" . $items->count() . "\n";
foreach($items as $i) {
    echo "ITEM:" . json_encode($i) . "\n";
}
