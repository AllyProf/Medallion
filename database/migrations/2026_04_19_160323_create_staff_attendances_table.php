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
        Schema::create('staff_attendances', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $blueprint->timestamp('check_in');
            $blueprint->timestamp('check_out')->nullable();
            $blueprint->integer('duration_minutes')->nullable();
            $blueprint->string('status')->default('active'); // active, completed
            $blueprint->string('location_branch')->nullable();
            $blueprint->foreignId('user_id')->constrained('users')->onDelete('cascade'); // The business owner
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
