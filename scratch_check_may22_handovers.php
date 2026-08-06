<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== HANDOVERS ASSIGNED TO MAY 22 ===\n";
$handovers = \App\Models\FinancialHandover::whereDate('handover_date', '2026-05-22')->get();
foreach ($handovers as $h) {
    echo "ID: " . $h->id 
        . " | Amount: " . number_format($h->amount) 
        . " | Dept: " . $h->department 
        . " | Shift ID: " . $h->bar_shift_id 
        . " | Notes: " . $h->notes 
        . " | Breakdown: " . json_encode($h->payment_breakdown) . "\n";
}

echo "\n=== SHIFTS FOR MAY 22 ===\n";
$shifts = \App\Models\BarShift::whereDate('opened_at', '2026-05-22')->get();
foreach ($shifts as $s) {
    echo "Shift ID: " . $s->id . " | Opened: " . $s->opened_at . " | Status: " . $s->status . "\n";
}
