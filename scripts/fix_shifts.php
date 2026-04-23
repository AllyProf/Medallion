<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use Carbon\Carbon;

$oldShiftId = 16;
$newShiftId = 17;

$oldShift = BarShift::find($oldShiftId);
if ($oldShift) {
    $oldShift->update(['status' => 'closed', 'closed_at' => Carbon::now()]);
    echo "Shift #16 Closed.\n";
}

$updatedOrders = BarOrder::where('bar_shift_id', $oldShiftId)
    ->whereDate('created_at', Carbon::now()->format('Y-m-d'))
    ->update(['bar_shift_id' => $newShiftId]);

echo "$updatedOrders orders moved to Shift #17.\n";
