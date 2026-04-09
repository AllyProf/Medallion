<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kitchen_order_item_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_order_item_id')->constrained('kitchen_order_items')->onDelete('cascade');
            $table->foreignId('food_item_extra_id')->constrained('food_item_extras')->onDelete('cascade');
            $table->string('extra_name'); // Snapshot of extra name at time of order
            $table->decimal('unit_price', 10, 2); // Price at time of order
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2); // Calculated: quantity * unit_price
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_item_extras');
    }
};
