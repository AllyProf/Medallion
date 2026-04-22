<?php
/**
 * FIX: Delete Duplicate Empty Open Shifts
 * =========================================
 * This script finds and deletes duplicate OPEN shifts that:
 * - Have 0 linked orders (empty/unused)
 * - Were opened on the same day as another active shift
 *
 * This typically happens when the "Open Shift" button is clicked twice accidentally.
 *
 * HOW TO RUN:
 * 1. Upload to your Laravel project root on cPanel
 * 2. Run via SSH: php fixes/delete_duplicate_empty_shifts.php
 * 3. Delete this file after running for security
 *
 * NOTE: The script will always KEEP the OLDEST shift (lowest ID) on each day.
 *       Only newer empty duplicates are removed.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarShift;
use App\Models\BarOrder;
use Illuminate\Support\Facades\DB;

echo "=== Fix: Delete Duplicate Empty Open Shifts ===\n\n";

// Find all OPEN shifts grouped by date
$openShifts = BarShift::where('status', 'open')
    ->orderBy('opened_at', 'asc')
    ->get();

echo "Total OPEN shifts found: {$openShifts->count()}\n\n";

// Group by date (YYYY-MM-DD)
$byDate = $openShifts->groupBy(function($s) {
    return \Carbon\Carbon::parse($s->opened_at)->format('Y-m-d');
});

$deletedCount = 0;
foreach ($byDate as $date => $shiftsOnDate) {
    if ($shiftsOnDate->count() <= 1) continue; // No duplicate on this date

    echo "Date: {$date} has {$shiftsOnDate->count()} open shifts — checking for empties...\n";

    // Keep the first (oldest) shift, check remaining ones
    $toCheck = $shiftsOnDate->slice(1); // Skip first (the real one)

    foreach ($toCheck as $shift) {
        $orderCount = BarOrder::where('bar_shift_id', $shift->id)->count();
        $label = 'S' . str_pad($shift->id, 6, '0', STR_PAD_LEFT);

        if ($orderCount === 0) {
            $shift->delete();
            $deletedCount++;
            echo "  [DELETED] Shift {$label} (ID: {$shift->id}) — 0 orders, safe to remove.\n";
        } else {
            echo "  [KEPT]    Shift {$label} (ID: {$shift->id}) — has {$orderCount} orders, cannot delete!\n";
        }
    }
}

echo "\n=== Done! Deleted {$deletedCount} duplicate empty shift(s). ===\n";

if ($deletedCount === 0) {
    echo "No duplicate empty shifts found. All shifts are clean!\n";
}
