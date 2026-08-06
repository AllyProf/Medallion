<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;

$s = Staff::where('email', 'glorygerald23@gmail.com')
    ->orWhere('full_name', 'like', '%GLORY GERALD%')
    ->with('role')
    ->first();

if (!$s) {
    echo "no staff\n";
    exit(1);
}

echo "staff id={$s->id} name={$s->full_name} role=" . ($s->role->slug ?? $s->role->name ?? 'n/a') . " user_id={$s->user_id}\n";

foreach (WaiterDailyReconciliation::where('waiter_id', $s->id)->where('reconciliation_type', 'bar')->orderByDesc('id')->get() as $r) {
    echo "recon#{$r->id} shift={$r->bar_shift_id} exp={$r->expected_amount} sub={$r->submitted_amount} diff={$r->difference} status={$r->status}\n";
}

$shift = BarShift::where('user_id', $s->user_id)->where('status', 'open')->orderByDesc('opened_at')->first();
echo 'open shift: ' . ($shift ? "#{$shift->id} opened {$shift->opened_at}" : 'none') . "\n";
