<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ownerId = 1;
$topProducts = \App\Models\OrderItem::with('productVariant.product')
    ->whereHas('order', function($q) use ($ownerId) {
        $q->where('user_id', $ownerId)
          ->where('payment_status', 'paid')
          ->whereMonth('created_at', now()->month);
    })
    ->whereNotNull('product_variant_id')
    ->selectRaw('product_variant_id, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
    ->groupBy('product_variant_id')
    ->orderByDesc('total_sold')
    ->limit(8)
    ->get()
    ->map(function($item) {
        $item->product_full_name = $item->productVariant ? $item->productVariant->display_name : 'Unknown Product';
        return $item;
    });

$maxSold = $topProducts->max('total_sold') ?: 1;
echo "MAX: $maxSold\n";
foreach($topProducts as $tp) {
    $pct = round(($tp->total_sold / $maxSold) * 100);
    echo "NAME: {$tp->product_full_name} | PCT: {$pct}% | WIDTH ATTR: width: {$pct}%\n";
}
