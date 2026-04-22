<?php
/**
 * FIX: Stale Shortage Records
 * ============================
 * This script fixes WaiterDailyReconciliation records where:
 * - submitted_amount == expected_amount (no real shortage)
 * - BUT the `difference` column still has a negative value (stale from partial payments)
 *
 * This caused false "MISSING" labels to appear in the Daily Master Sheet Archive.
 *
 * HOW TO RUN:
 * 1. Upload this file to your Laravel project root on cPanel
 * 2. Run via SSH: php fixes/fix_stale_shortages.php
 * 3. Delete the file after running for security
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WaiterDailyReconciliation;

echo "=== Fix: Stale Shortage Records ===\n\n";

// Find records where submitted == expected but difference is negative (stale)
$staleRecs = WaiterDailyReconciliation::whereRaw('ABS(submitted_amount - expected_amount) < 1')
    ->where('difference', '<', 0)
    ->get();

echo "Stale records found: {$staleRecs->count()}\n\n";

foreach ($staleRecs as $r) {
    echo "  ID: {$r->id} | Waiter ID: {$r->waiter_id}";
    echo " | Expected: {$r->expected_amount} | Submitted: {$r->submitted_amount}";
    echo " | Stale Difference: {$r->difference} | Status: {$r->status}\n";
}

if ($staleRecs->count() === 0) {
    echo "Nothing to fix. All records are clean!\n";
    exit(0);
}

echo "\nFixing...\n";
foreach ($staleRecs as $r) {
    $r->difference = 0;
    $r->status = 'reconciled';
    $r->save();
    echo "  Fixed record ID: {$r->id}\n";
}

echo "\nDone! Stale shortage records corrected.\n";
echo "The false 'MISSING' labels will be gone from the Master Sheet Archive.\n";
