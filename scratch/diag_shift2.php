<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$shiftId = 2; // S000002
$orders = \App\Models\BarOrder::where('bar_shift_id', $shiftId)
    ->whereIn('status', ['served', 'delivered'])
    ->with('items.productVariant')
    ->get();

echo "Orders count for Shift $shiftId: " . $orders->count() . "\n";
$profit = 0;
foreach ($orders as $o) {
    foreach ($o->items as $item) {
        $buyingPrice = $item->productVariant->buying_price_per_unit ?? 0;
        $p = ($item->unit_price - $buyingPrice) * $item->quantity;
        $profit += $p;
    }
}
echo "Calculated Profit for Shift $shiftId: " . $profit . "\n";

$handover = \App\Models\FinancialHandover::where('bar_shift_id', $shiftId)->first();
echo "Handover Date for Shift $shiftId: " . ($handover->handover_date ?? 'NONE') . "\n";
