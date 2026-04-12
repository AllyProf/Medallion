<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reconciliation = \App\Models\WaiterDailyReconciliation::updateOrCreate(
    [
        'user_id' => 1,
        'waiter_id' => 15,
        'reconciliation_date' => '2026-04-11',
        'reconciliation_type' => 'food'
    ],
    [
        'notes' => "Test shortage"
    ]
);

$shortageAmount = 1000;

var_dump($reconciliation->total_sales);

if (empty(floatval($reconciliation->total_sales))) {
    $reconciliation->difference = -$shortageAmount;
    $reconciliation->status = 'partial';
    $reconciliation->save();
    echo "Path 1 executed\n";
} else {
    $reconciliation->submitted_amount -= $shortageAmount;
    $reconciliation->difference = $reconciliation->submitted_amount - $reconciliation->expected_amount;
    $reconciliation->status = 'partial';
    $reconciliation->save();
    echo "Path 2 executed\n";
}

echo "Difference: " . $reconciliation->difference . "\n";
