<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$o167 = \App\Models\BarOrder::find(167);
if ($o167) {
    echo "167_DATA:" . json_encode($o167) . "\n";
} else {
    echo "167_NOT_FOUND\n";
}
