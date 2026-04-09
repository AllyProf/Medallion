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
        // MySQL specific change for ENUM
        DB::statement("ALTER TABLE kitchen_order_items MODIFY COLUMN status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE kitchen_order_items MODIFY COLUMN status ENUM('pending', 'preparing', 'ready', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
