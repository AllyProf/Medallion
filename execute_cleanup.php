<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    echo "--- Medallion Data Cleanup Utility ---\n\n";

    // 1. Delete Shift 6
    $shift6 = App\Models\BarShift::find(6);
    if ($shift6) {
        $id = $shift6->id;
        $shift6->delete();
        echo "[SUCCESS] Deleted Shift #S000006 (ID: {$id})\n";
    } else {
        echo "[INFO] Shift #S000006 already deleted or not found.\n";
    }

    // 2. Consolidate Staff Locations
    $staffCount = App\Models\Staff::where('location_branch', '!=', 'MOSHI-KILIMANJARO')
        ->orWhereNull('location_branch')
        ->update(['location_branch' => 'MOSHI-KILIMANJARO']);
    echo "[SUCCESS] Updated {$staffCount} staff members to location: MOSHI-KILIMANJARO\n";

    // 3. Consolidate Bar Shift Locations
    $shiftCount = App\Models\BarShift::where('location_branch', '!=', 'MOSHI-KILIMANJARO')
        ->orWhereNull('location_branch')
        ->update(['location_branch' => 'MOSHI-KILIMANJARO']);
    echo "[SUCCESS] Updated {$shiftCount} bar shifts to location: MOSHI-KILIMANJARO\n";

    DB::commit();
    echo "\n[DONE] Transaction committed successfully. You may safely delete this file.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
