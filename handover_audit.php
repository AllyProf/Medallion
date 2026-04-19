<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- HANDOVERS ---\n";
$handovers = DB::table('financial_handovers')->get();
foreach($handovers as $h) {
    echo "H_ID: {$h->id} | Shift: {$h->bar_shift_id} | Date: {$h->handover_date} | Verified: {$h->verified}\n";
}

echo "\n--- BAR SHIFTS ---\n";
$shifts = DB::table('bar_shifts')->get();
foreach($shifts as $s) {
    echo "S_ID: {$s->id} | Opened: {$s->opened_at}\n";
}
