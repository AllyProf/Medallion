<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    Schema::table('financial_handovers', function (Blueprint $table) {
        $table->unsignedBigInteger('accountant_id')->nullable()->change();
    });
    echo "Column accountant_id successfully made nullable.";
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage();
}
