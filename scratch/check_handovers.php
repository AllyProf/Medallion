<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinancialHandover;
use App\Models\BarShift;

echo "--- CHECKING HANDOVERS TODAY ---\n";
$handovers = FinancialHandover::whereDate('handover_date', now()->format('Y-m-d'))->get();
echo "Handovers today: " . $handovers->count() . "\n";
foreach($handovers as $h) {
    echo "  ID: {$h->id} | Status: {$h->status} | Amount: {$h->amount} | From (Staff ID): {$h->staff_id} | To (Accountant ID): {$h->accountant_id}\n";
}

echo "\n--- CHECKING OPEN SHIFTS ---\n";
$shifts = BarShift::where('status', 'open')->get();
echo "Open shifts: " . $shifts->count() . "\n";
foreach($shifts as $s) {
    echo "  ID: {$s->id} | Staff ID: {$s->staff_id} | Date: {$s->shift_date} | User ID: {$s->user_id}\n";
}
