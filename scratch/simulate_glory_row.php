<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarOrder;
use App\Models\BarShift;
use App\Models\Staff;

$s = Staff::with('role')->where('email', 'glorygerald23@gmail.com')->first();
$shift = BarShift::where('user_id', $s->user_id)->where('status', 'open')->orderByDesc('opened_at')->first();

foreach ([false, true] as $useOpened) {
    $q = BarOrder::where('waiter_id', $s->id)->where('user_id', $s->user_id)
        ->where('status', '!=', 'cancelled')->whereHas('items');
    if ($shift) {
        $q->where(function ($w) use ($shift) {
            $w->where('bar_shift_id', $shift->id)
              ->orWhere(fn ($x) => $x->whereNull('bar_shift_id')->whereDate('created_at', today()));
        });
        if ($useOpened) {
            $q->where('created_at', '>=', $shift->opened_at);
        }
    }
    $orders = $q->with('items')->get();
    $sales = $orders->sum(fn ($o) => $o->items->sum('total_price'));
    echo ($useOpened ? 'strict' : 'loose') . " shift#{$shift?->id} orders={$orders->count()} sales={$sales}\n";
}

$rec = \App\Models\WaiterDailyReconciliation::where('waiter_id', $s->id)
    ->where('reconciliation_type', 'bar')
    ->where('bar_shift_id', $shift?->id)
    ->first();
echo 'recon shift63: ' . ($rec ? "#{$rec->id} diff={$rec->difference}" : 'none') . "\n";
echo "role {$s->role->name}/{$s->role->slug}\n";
