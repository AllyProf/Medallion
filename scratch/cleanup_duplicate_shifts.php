<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;
use Illuminate\Support\Facades\DB;

echo "--- Duplicate Shift Cleanup Tool ---\n";

// Target labels to delete (as per user request: S000020 and S000019)
// Since labels are usually formatted IDs, we look for IDs 20 and 19.
$idsToDelete = [19, 20];

DB::beginTransaction();
try {
    foreach ($idsToDelete as $id) {
        $shift = BarShift::find($id);
        if ($shift) {
            echo "Deleting Shift ID: $id (Opened At: {$shift->opened_at})\n";
            $shift->delete();
        } else {
            echo "Shift ID: $id not found, skipping.\n";
        }
    }
    
    DB::commit();
    echo "SUCCESS: Duplicate shifts deleted.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "FAILED: " . $e->getMessage() . "\n";
}
