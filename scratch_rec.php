<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rec = \App\Models\WaiterDailyReconciliation::find(4);
echo "Rec difference: " . $rec->difference . "\n";
echo "Rec total_sales: " . $rec->total_sales . "\n";
echo "Rec expected_amount: " . $rec->expected_amount . "\n";
echo "Rec submitted_amount: " . $rec->submitted_amount . "\n";
echo "Rec notes: " . $rec->notes . "\n";
