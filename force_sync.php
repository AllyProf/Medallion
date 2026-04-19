<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;
use Illuminate\Support\Facades\DB;

echo "--- FORCING LEDGER SYNC FOR ALL USERS --- \n";

$logs = DB::table('daily_cash_ledgers')->get();
foreach($logs as $log) {
    $ledger = DailyCashLedger::find($log->id);
    if($ledger) {
        echo "[*] Syncing {$ledger->ledger_date->format('Y-m-d')} for User {$ledger->user_id}... ";
        $ledger->syncTotals();
        $ledger->save();
        echo "[New Cash: " . $ledger->total_cash_received . "]\n";
    }
}

echo "--- DONE ---\n";
