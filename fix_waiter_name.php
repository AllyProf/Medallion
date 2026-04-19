<?php
use App\Models\WaiterDailyReconciliation;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recs = WaiterDailyReconciliation::where('notes', 'LIKE', '%152000%')->get();
foreach ($recs as $r) {
    if (strpos($r->notes, '2026-04-19') !== false) {
        $r->notes = str_replace('2026-04-19', '2026-04-17', $r->notes);
        $r->updated_at = '2026-04-17 21:00:00';
        $r->save();
        echo "Updated DB Record ID: " . $r->id . "\n";
    }
}
echo "Done!\n";
