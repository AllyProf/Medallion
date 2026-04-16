<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinancialHandover;

$h = FinancialHandover::find(1);
if ($h) {
    echo "Handover 1:\n";
    echo "  Shift ID: {$h->bar_shift_id}\n";
    echo "  Status: {$h->status}\n";
    echo "  Staff (From): {$h->staff_id}\n";
    echo "  Accountant (To): {$h->accountant_id}\n";
} else {
    echo "Handover 1 not found.\n";
}
