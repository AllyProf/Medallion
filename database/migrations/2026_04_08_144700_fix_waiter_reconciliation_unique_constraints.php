<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
                $table->dropUnique('wd_waiter_date_unique');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
                $table->dropUnique('wd_waiter_date_type_unique');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
                $table->unique(['waiter_id', 'reconciliation_date', 'reconciliation_type', 'bar_shift_id'], 'wd_waiter_date_type_shift_unique');
            });
        } catch (\Exception $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
            $table->dropUnique('wd_waiter_date_type_shift_unique');

            // Re-add the previous ones
            $table->unique(['waiter_id', 'reconciliation_date', 'reconciliation_type'], 'wd_waiter_date_type_unique');
            $table->unique(['waiter_id', 'reconciliation_date'], 'wd_waiter_date_unique');
        });
    }
};
