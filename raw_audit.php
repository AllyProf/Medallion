<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$shifts = DB::table('bar_shifts')->select('id', 'opened_at', 'closed_at')->get();
echo "RAW SHIFTS:\n";
foreach($shifts as $s) {
    echo "ID: {$s->id} | opened_at: {$s->opened_at} | closed_at: {$s->closed_at}\n";
}

$handovers = DB::table('financial_handovers')->select('id', 'bar_shift_id', 'handover_date')->get();
echo "\nRAW HANDOVERS:\n";
foreach($handovers as $h) {
    echo "ID: {$h->id} | bar_shift_id: {$h->bar_shift_id} | handover_date: {$h->handover_date}\n";
}
