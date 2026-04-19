<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$currentId = 7;
$newId = 6;

try {
    // Disable Foreign Key Checks globally for this connection session
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    DB::beginTransaction();

    // 1. Rename the Shift itself
    if (DB::table('bar_shifts')->where('id', $currentId)->exists()) {
        echo "Found Shift #7. Renaming to #6...\n";
        DB::table('bar_shifts')->where('id', $currentId)->update(['id' => $newId]);
    }

    // 2. Update all tables that have bar_shift_id
    $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'bar_shift_id' AND TABLE_SCHEMA = DATABASE()");
    foreach($tables as $t) {
        $tableName = $t->TABLE_NAME;
        $count = DB::table($tableName)->where('bar_shift_id', $currentId)->update(['bar_shift_id' => $newId]);
        if ($count > 0) echo "Updated $count rows in $tableName.\n";
    }
    
    // 3. Special handling for ORD-166 and ORD-167 to ensure they are definitely on #6
    $updatedCount = DB::table('orders')
        ->whereIn('order_number', ['ORD-166', 'ORD-167'])
        ->update(['bar_shift_id' => $newId]);
    echo "Confirmed ORD-166/167 point to Shift #6.\n";

    DB::commit();
    echo "SUCCESS: Shift sequence corrected and orders aligned.\n";

} catch (\Exception $e) {
    echo "ERROR during data repair: " . $e->getMessage() . "\n";
    DB::rollBack();
} finally {
    // ALWAYS re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}
