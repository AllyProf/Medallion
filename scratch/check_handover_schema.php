<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinancialHandover;

$h = FinancialHandover::first();
if ($h) {
    echo "Columns in financial_handovers:\n";
    print_r(array_keys($h->getAttributes()));
} else {
    echo "No handovers found.\n";
}
