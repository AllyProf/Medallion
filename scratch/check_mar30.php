<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\DailyCashLedger;
$l = DailyCashLedger::with(['expenses'])->where('ledger_date', '2026-03-30')->first();
if($l){
    echo json_encode($l->toArray(), JSON_PRETTY_PRINT);
} else {
    echo "Not found";
}
