<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$o = \App\Models\BarOrder::where('order_number', 'ORD-167')->first();
if ($o) {
    echo "FOUND_ORD_167_WITH_ID:" . $o->id . " Shift:" . $o->bar_shift_id . "\n";
} else {
    echo "ORD_167_STILL_MISSING\n";
    // Search by approximate match
    $any = \App\Models\BarOrder::where('order_number', 'LIKE', '%167%')->get();
    echo "LIKE_MATCHES:" . $any->count() . "\n";
}
