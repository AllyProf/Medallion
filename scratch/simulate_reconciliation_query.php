<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staff;
use App\Models\BarShift;
use App\Models\WaiterDailyReconciliation;

$ownerId = 4;
$date = now()->format('Y-m-d');
$location = null; // Simulation: No branch filter

// Simulate Controller Logic
$targetShiftIds = BarShift::where('user_id', $ownerId)
    ->where('status', 'open')
    ->pluck('id')
    ->toArray();

echo "Target Shift IDs: " . implode(', ', $targetShiftIds) . "\n";

$waitersQuery = Staff::where('is_active', true)
    ->where('user_id', $ownerId)
    ->where(function ($query) use ($date, $location, $targetShiftIds) {
        $query->whereHas('role', function ($q) {
            $q->where('slug', 'waiter');
        })
        ->orWhereHas('orders', function ($q) use ($date, $targetShiftIds) {
            if (!empty($targetShiftIds)) {
                $q->whereIn('bar_shift_id', $targetShiftIds);
            } else {
                $q->whereDate('created_at', $date);
            }
        })
        ->orWhereHas('dailyReconciliations', function ($q) use ($date, $targetShiftIds) {
            if (!empty($targetShiftIds)) {
                $q->whereIn('bar_shift_id', $targetShiftIds);
            } else {
                $q->where('reconciliation_date', $date);
            }
            $q->where('reconciliation_type', 'bar');
        });
    });

$count = $waitersQuery->count();
echo "Total Waiters Found: $count\n";

$waiters = $waitersQuery->get();
foreach($waiters as $w) {
    echo "  - ID: {$w->id} | Name: {$w->full_name} | Branch: [{$w->location_branch}]\n";
}

// Test with a location
$testLocation = 'SomeBranch'; // Example
echo "\nTesting with location 'SomeBranch':\n";
$waitersQueryLocation = clone $waitersQuery;
$waitersQueryLocation->where('location_branch', $testLocation);
echo "Waiters with 'SomeBranch': " . $waitersQueryLocation->count() . "\n";
