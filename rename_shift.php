<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$currentId = 7;
$newId = 6;

try {
    DB::beginTransaction();

    // 1. Check if 6 already exists (just in case)
    if (DB::table('bar_shifts')->where('id', $newId)->exists()) {
        echo "SHIFT_6_ALREADY_EXISTS\n";
        DB::rollBack();
        exit;
    }

    // 2. Disable FK checks for the ID swap if necessary, but better to update related tables first
    // Models typically use bar_shift_id
    
    // Find all tables with bar_shift_id column
    $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'bar_shift_id' AND TABLE_SCHEMA = DATABASE()");
    
    echo "Updating related tables...\n";
    foreach($tables as $t) {
        $tableName = $t->TABLE_NAME;
        $count = DB::table($tableName)->where('bar_shift_id', $currentId)->update(['bar_shift_id' => $newId]);
        echo "Updated $count rows in $tableName\n";
    }
    
    // 3. Update the shift ID itself
    // We can't easily update a primary key directly if it's Auto Increment in some DBs without turning it off
    // But in MySQL we can usually DO it if no FKs block it.
    
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $updated = DB::table('bar_shifts')->where('id', $currentId)->update(['id' => $newId]);
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    if ($updated) {
        echo "SHIFT_7_SUCCESSFULLY_RENAMED_TO_6\n";
        DB::commit();
    } else {
        echo "FAILED_TO_RENAME_SHIFT\n";
        DB::rollBack();
    }

} catch (\Exception $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
    DB::rollBack();
}
