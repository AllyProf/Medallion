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
        Schema::table('stock_transfers', function (Blueprint $table) {
            // Add staff-specific tracking columns (separate from user-based columns)
            $table->unsignedBigInteger('requested_by_staff_id')->nullable()->after('requested_by');
            $table->unsignedBigInteger('approved_by_staff_id')->nullable()->after('approved_by');
            $table->unsignedBigInteger('verified_by_staff_id')->nullable()->after('verified_by');

            $table->foreign('requested_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('approved_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('verified_by_staff_id')->references('id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['requested_by_staff_id']);
            $table->dropForeign(['approved_by_staff_id']);
            $table->dropForeign(['verified_by_staff_id']);
            $table->dropColumn(['requested_by_staff_id', 'approved_by_staff_id', 'verified_by_staff_id']);
        });
    }
};
