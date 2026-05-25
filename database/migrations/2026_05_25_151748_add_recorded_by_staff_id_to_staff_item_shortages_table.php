<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_item_shortages', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_item_shortages', 'recorded_by_staff_id')) {
                $table->unsignedBigInteger('recorded_by_staff_id')->nullable()->after('recorded_by');
            }
            if (!Schema::hasColumn('staff_item_shortages', 'approved_by_staff_id')) {
                $table->unsignedBigInteger('approved_by_staff_id')->nullable()->after('recorded_by_staff_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_item_shortages', function (Blueprint $table) {
            $table->dropColumnIfExists('recorded_by_staff_id');
            $table->dropColumnIfExists('approved_by_staff_id');
        });
    }
};
