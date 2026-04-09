<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$v = \App\Models\ProductVariant::where('name', 'Dodoma Red (Dry)')->first();
if ($v) {
    echo "Variant: " . $v->name . "\n";
    echo "Measurement: " . $v->measurement . "\n";
    echo "Total Tots: " . $v->total_tots . "\n";
    echo "Selling Price (Btl): " . $v->selling_price_per_unit . "\n";
    echo "Selling Price (Tot): " . $v->selling_price_per_tot . "\n";
} else {
    echo "Variant not found\n";
}
