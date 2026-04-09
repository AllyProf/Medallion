<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
        // Create a temporary non-unique index to support the FK
        $table->index('waiter_id', 'tmp_waiter_id_idx');
    });
    echo "Added tmp_waiter_id_idx\n";

    Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
        $table->dropUnique('wd_waiter_date_type_unique');
    });
    echo "Dropped wd_waiter_date_type_unique\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
