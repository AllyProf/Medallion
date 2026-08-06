<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarOrder;
use App\Models\BarShift;

$shiftIds = BarShift::whereDate('opened_at', '2026-06-06')->pluck('id');
$orders = BarOrder::whereIn('bar_shift_id', $shiftIds)
    ->whereIn('status', ['served', 'delivered'])
    ->with(['items.productVariant', 'items.transferSales.stockTransfer'])
    ->get();

$ledgerStyle = 0;
$fixedStyle = 0;
$badItems = [];

foreach ($orders as $order) {
    foreach ($order->items as $item) {
        $variant = $item->productVariant;
        $buyingPrice = $variant->buying_price_per_unit ?? 0;

        // Ledger formula (DailyCashLedger)
        $ledgerProfit = ($item->unit_price - $buyingPrice) * $item->quantity;

        // Counter reconciliation style with tot adjustment + transfer sales
        $itemProfit = 0;
        if ($item->transferSales->count() > 0) {
            foreach ($item->transferSales as $ts) {
                $whStock = \App\Models\StockLocation::where('user_id', $order->user_id)
                    ->where('product_variant_id', $ts->stockTransfer->product_variant_id)
                    ->where('location', 'warehouse')
                    ->first();
                $buy = $whStock->average_buying_price ?? $variant->buying_price_per_unit ?? 0;
                $itemProfit += ($ts->total_price - ($ts->quantity * $buy));
            }
        } else {
            $qty = $item->quantity;
            if (($item->sell_type ?? 'unit') === 'tot' && $variant) {
                $totsPerBtl = $variant->total_tots ?: 1;
                $qty = $item->quantity / $totsPerBtl;
            }
            $buy = $variant->buying_price_per_unit ?? 0;
            $itemProfit = ($item->total_price - ($qty * $buy));
        }

        $ledgerStyle += $ledgerProfit;
        $fixedStyle += $itemProfit;

        if (abs($ledgerProfit - $itemProfit) > 100) {
            $badItems[] = [
                'order' => $order->order_number,
                'item' => $variant->display_name ?? '?',
                'sell_type' => $item->sell_type ?? 'unit',
                'qty' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'buying' => $buyingPrice,
                'ledger' => $ledgerProfit,
                'fixed' => $itemProfit,
            ];
        }
    }
}

echo "Ledger formula gross profit: {$ledgerStyle}\n";
echo "Counter-style gross profit: {$fixedStyle}\n";
echo "Sales total: " . $orders->sum(fn ($o) => $o->items->sum('total_price')) . "\n";
echo "\nMismatch items (" . count($badItems) . "):\n";
foreach (array_slice($badItems, 0, 15) as $b) {
    echo "  {$b['order']} {$b['item']} ({$b['sell_type']} qty={$b['qty']}) sell={$b['total_price']} buy/unit={$b['buying']} ledger={$b['ledger']} fixed={$b['fixed']}\n";
}
