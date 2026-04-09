<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

$ownerId = 1;
$location = null;

$topProducts = \App\Models\OrderItem::with('productVariant.product')
    ->whereHas('order', function($q) use ($ownerId, $location) {
        $q->where('user_id', $ownerId)
          ->where('payment_status', 'paid')
          ->whereMonth('created_at', now()->month);
        if ($location) {
            $q->where(function($sq) use ($location) {
                $sq->whereExists(function ($ssq) use ($location) {
                    $ssq->select(DB::raw(1))
                       ->from('staff')
                       ->whereColumn('staff.id', 'orders.waiter_id')
                       ->where('staff.location_branch', $location);
                })->orWhereHas('table', function($ssq) use ($location) {
                    $ssq->where('location', $location);
                });
            });
        }
    })
    ->selectRaw('product_variant_id, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
    ->groupBy('product_variant_id')
    ->orderByDesc('total_sold')
    ->limit(8)
    ->get()
    ->map(function($item) {
        $item->display_name = $item->productVariant ? $item->productVariant->display_name : 'Unknown Product';
        return $item;
    });

foreach($topProducts as $tp) {
    echo "NAME: '{$tp->display_name}' | SOLD: {$tp->total_sold}\n";
}
echo "MAX SOLD: " . $topProducts->max('total_sold') . "\n";
