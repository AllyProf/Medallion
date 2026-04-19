<?php
require __DIR__ . '/../../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;
use App\Models\FinancialHandover;

$idsToDelete = [8, 9, 10, 11];

foreach ($idsToDelete as $id) {
    $shift = BarShift::find($id);
    if ($shift) {
        echo "Processing Shift ID: $id\n";
        
        // Count orders
        $orderCount = BarOrder::where('bar_shift_id', $id)->count();
        echo "- Linked Orders: $orderCount\n";
        
        // Count Reconciliations
        $recCount = WaiterDailyReconciliation::where('shift_id', $id)->count();
        echo "- Linked Reconciliations: $recCount\n";
        
        // Count Handovers
        $handoverCount = FinancialHandover::where('bar_shift_id', $id)->count();
        echo "- Linked Handovers: $handoverCount\n";
        
        if ($orderCount == 0 && $recCount == 0 && $handoverCount == 0) {
            echo "- Safety check passed. No critical production data found. Deleting...\n";
            $shift->delete();
            echo "- DELETED.\n";
        } else {
            echo "- [WARNING] Shift has linked data! Will NOT delete automatically without further instructions.\n";
            // If the user wants them gone regardless, I'll need to null the foreign keys or delete them too.
            // But usually 'open' duplicate shifts don't have data yet.
        }
    } else {
        echo "Shift ID: $id not found.\n";
    }
}
