<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_item_shortages', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_item_shortages', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_staff_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_item_shortages', function (Blueprint $table) {
            if (Schema::hasColumn('staff_item_shortages', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
