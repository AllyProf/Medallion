<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

function dropIndexIfExists($table, $index)
{
    try {
        Schema::table($table, function (Blueprint $tableObj) use ($index) {
            $tableObj->dropUnique($index);
        });
        echo "Dropped $index\n";
    } catch (\Exception $e) {
        echo "Could not drop $index: " . $e->getMessage() . "\n";
    }
}

dropIndexIfExists('waiter_daily_reconciliations', 'wd_waiter_date_unique');
dropIndexIfExists('waiter_daily_reconciliations', 'wd_waiter_date_type_unique');

try {
    Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
        $table->unique(['waiter_id', 'reconciliation_date', 'reconciliation_type', 'bar_shift_id'], 'wd_waiter_date_type_shift_unique');
    });
    echo "Added wd_waiter_date_type_shift_unique\n";
} catch (\Exception $e) {
    echo "Could not add wd_waiter_date_type_shift_unique: " . $e->getMessage() . "\n";
}
