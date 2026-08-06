<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\DailyCashLedger;
use App\Models\BarOrder;

echo "--- SHIFTS ---\n";
$shift24 = BarShift::find(24);
if ($shift24) {
    echo "S000024 (ID:24): opened={$shift24->opened_at}, closed={$shift24->closed_at}, status={$shift24->status}\n";
} else {
    echo "ID 24 not found.\n";
}

$shift25 = BarShift::find(25);
if ($shift25) {
    echo "S000025 (ID:25): opened={$shift25->opened_at}, closed={$shift25->closed_at}, status={$shift25->status}\n";
    $orders25 = BarOrder::where('bar_shift_id', $shift25->id)->count();
    echo "  Orders in S000025: $orders25\n";
} else {
    echo "ID 25 not found.\n";
}

echo "\n--- LEDGERS ---\n";
$l28 = DailyCashLedger::where('ledger_date', '2026-04-28')->first();
if ($l28) {
    echo "Apr 28 Ledger: status={$l28->status}, closed_at={$l28->closed_at}\n";
} else {
    echo "Apr 28 Ledger not found.\n";
}

$l29 = DailyCashLedger::where('ledger_date', '2026-04-29')->first();
if ($l29) {
    echo "Apr 29 Ledger: status={$l29->status}, closed_at={$l29->closed_at}\n";
} else {
    echo "Apr 29 Ledger not found.\n";
}
