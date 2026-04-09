<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
            $table->unsignedBigInteger('bar_shift_id')->nullable()->after('waiter_id');
            $table->foreign('bar_shift_id')->references('id')->on('bar_shifts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiter_daily_reconciliations', function (Blueprint $table) {
            $table->dropForeign(['bar_shift_id']);
            $table->dropColumn('bar_shift_id');
        });
    }
};
