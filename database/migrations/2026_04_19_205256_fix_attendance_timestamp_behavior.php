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
        Schema::table('staff_attendances', function (Blueprint $table) {
            // Changing to dateTime avoids the MySQL auto-update behavior on timestamp columns
            $table->dateTime('check_in')->change();
            $table->dateTime('check_out')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->timestamp('check_in')->change();
            $table->timestamp('check_out')->nullable()->change();
        });
    }
};
