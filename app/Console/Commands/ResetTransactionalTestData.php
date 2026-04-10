<?php

namespace App\Console\Commands;

use App\Models\BarOrder;
use App\Models\KitchenOrderItem;
use App\Models\StockReceipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTransactionalTestData extends Command
{
    protected $signature = 'testing:reset-transactional-data
                            {--force : Run without confirmation (required when not in local/testing)}';

    protected $description = 'Delete orders, stock receipts, cash ledger / handover / reconciliation data for fresh testing (keeps catalog, users, staff, stock transfers).';

    public function handle(): int
    {
        $env = app()->environment();
        if (! in_array($env, ['local', 'testing'], true) && ! $this->option('force')) {
            $this->error('Refusing to run outside local/testing without --force.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This permanently deletes orders, receipts, and master-sheet data. Continue?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $driver = Schema::getConnection()->getDriverName();

        Schema::disableForeignKeyConstraints();

        try {
            DB::table('ingredient_stock_movements')
                ->where('reference_type', KitchenOrderItem::class)
                ->delete();

            DB::table('stock_movements')->where(function ($q) {
                $q->where('reference_type', BarOrder::class)
                    ->orWhere('reference_type', StockReceipt::class)
                    ->orWhere('reference_type', 'stock_receipt');
            })->delete();

            $truncate = [
                'transfer_sales',
                'kitchen_order_item_extras',
                'food_order_ingredients',
                'kitchen_order_items',
                'order_payments',
                'order_items',
                'orders',
                'waiter_sms_notifications',
                'waiter_notifications',
                'open_bottles',
                'stock_receipts',
                'daily_expenses',
                'daily_cash_ledgers',
                'waiter_daily_reconciliations',
                'financial_handovers',
                'petty_cash_issues',
                'bar_shifts',
            ];

            foreach ($truncate as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                if ($driver === 'mysql') {
                    DB::table($table)->truncate();
                } else {
                    DB::table($table)->delete();
                }
            }

            if (Schema::hasTable('payments')) {
                if ($driver === 'mysql') {
                    DB::table('payments')->truncate();
                } else {
                    DB::table('payments')->delete();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Transactional test data cleared.');
        $this->comment('Note: Stock location quantities and ingredient batches are unchanged; only receipt/order-linked movements were removed. Recount or adjust stock if figures look wrong.');

        return self::SUCCESS;
    }
}
