<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL for safety when altering columns with existing data
        
        // 1. Stock Receipts
        if (Schema::hasTable('stock_receipts')) {
            DB::statement('ALTER TABLE stock_receipts MODIFY quantity_received DECIMAL(10,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_receipts MODIFY total_units DECIMAL(10,2) NOT NULL DEFAULT 0');
        }

        // 2. Stock Locations
        if (Schema::hasTable('stock_locations')) {
            DB::statement('ALTER TABLE stock_locations MODIFY quantity DECIMAL(10,2) NOT NULL DEFAULT 0');
        }

        // 3. Stock Movements
        if (Schema::hasTable('stock_movements')) {
            DB::statement('ALTER TABLE stock_movements MODIFY quantity DECIMAL(10,2) NOT NULL DEFAULT 0');
        }
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_receipts')) {
            DB::statement('ALTER TABLE stock_receipts MODIFY quantity_received INT(11) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_receipts MODIFY total_units INT(11) NOT NULL DEFAULT 0');
        }

        if (Schema::hasTable('stock_locations')) {
            DB::statement('ALTER TABLE stock_locations MODIFY quantity INT(11) NOT NULL DEFAULT 0');
        }

        if (Schema::hasTable('stock_movements')) {
            DB::statement('ALTER TABLE stock_movements MODIFY quantity INT(11) NOT NULL DEFAULT 0');
        }
    }
};
