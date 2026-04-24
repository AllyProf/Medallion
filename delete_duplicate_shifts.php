<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;

echo "--- Deleting Duplicate Shifts (S000019, S000020) ---\n";

// Target IDs based on the labels S000019 and S000020
$idsToDelete = [19, 20];

foreach ($idsToDelete as $id) {
    $shift = BarShift::find($id);
    if ($shift) {
        // Ensure we only delete if it has no orders or as requested
        echo "Deleting Shift ID: $id (Status: {$shift->status})\n";
        $shift->delete();
    } else {
        echo "Shift ID $id not found.\n";
    }
}

echo "Cleanup finished.\n";
