<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- BAR SHIFTS --- \n";
$shifts = DB::table('bar_shifts')->get();
foreach($shifts as $s) {
    echo "ID: " . ($s->id ?? 'n/a') . " | CODE: " . ($s->shift_code ?? 'n/a') . " | OPENED: " . ($s->opened_at ?? 'n/a') . "\n";
}
