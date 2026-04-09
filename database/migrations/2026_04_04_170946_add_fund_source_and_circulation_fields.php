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
        Schema::table('daily_expenses', function (Blueprint $table) {
            $table->string('fund_source')->default('circulation')->after('amount'); // circulation, profit
        });

        Schema::table('daily_cash_ledgers', function (Blueprint $table) {
            $table->decimal('total_expenses_from_circulation', 15, 2)->default(0)->after('total_expenses');
            $table->decimal('total_expenses_from_profit', 15, 2)->default(0)->after('total_expenses_from_circulation');
            $table->decimal('money_in_circulation', 15, 2)->default(0)->after('profit_generated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            $table->dropColumn('fund_source');
        });

        Schema::table('daily_cash_ledgers', function (Blueprint $table) {
            $table->dropColumn(['total_expenses_from_circulation', 'total_expenses_from_profit', 'money_in_circulation']);
        });
    }
};
