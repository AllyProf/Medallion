<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
        echo "Dropping wd_waiter_date_unique...\n";
        try {
            $table->dropUnique('wd_waiter_date_unique');
            echo "Successfully dropped wd_waiter_date_unique.\n";
        } catch (\Exception $e) {
            echo "Failed to drop wd_waiter_date_unique: " . $e->getMessage() . "\n";
        }

        echo "Dropping wd_waiter_date_type_unique...\n";
        try {
            $table->dropUnique('wd_waiter_date_type_unique');
            echo "Successfully dropped wd_waiter_date_type_unique.\n";
        } catch (\Exception $e) {
            echo "Failed to drop wd_waiter_date_type_unique: " . $e->getMessage() . "\n";
        }

        echo "Adding wd_waiter_date_type_shift_unique...\n";
        $table->unique(['waiter_id', 'reconciliation_date', 'reconciliation_type', 'bar_shift_id'], 'wd_waiter_date_type_shift_unique');
        echo "Successfully added wd_waiter_date_type_shift_unique.\n";
    });
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
