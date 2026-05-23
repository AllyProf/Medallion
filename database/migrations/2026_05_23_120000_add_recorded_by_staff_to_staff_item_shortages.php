<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_item_shortages', function (Blueprint $table) {
            // Store the counter staff who physically recorded the shortage
            $table->unsignedBigInteger('recorded_by_staff_id')->nullable()->after('recorded_by');
            $table->foreign('recorded_by_staff_id')->references('id')->on('staff')->onDelete('set null');

            // Track who approved the shortage (manager/accountant)
            $table->unsignedBigInteger('approved_by_staff_id')->nullable()->after('recorded_by_staff_id');
            $table->foreign('approved_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->after('approved_by_staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('staff_item_shortages', function (Blueprint $table) {
            $table->dropForeign(['recorded_by_staff_id']);
            $table->dropForeign(['approved_by_staff_id']);
            $table->dropColumn(['recorded_by_staff_id', 'approved_by_staff_id', 'approved_at']);
        });
    }
};
