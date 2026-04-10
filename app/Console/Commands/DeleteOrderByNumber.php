<?php

namespace App\Console\Commands;

use App\Models\BarOrder;
use App\Models\KitchenOrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteOrderByNumber extends Command
{
    protected $signature = 'orders:delete
                            {order_number : e.g. ORD-03}
                            {--force : Run without confirmation (required when not in local/testing)}';

    protected $description = 'Permanently delete an order and all related rows (drink items, kitchen items, payments, movements).';

    public function handle(): int
    {
        $env = app()->environment();
        if (! in_array($env, ['local', 'testing'], true) && ! $this->option('force')) {
            $this->error('Refusing to run outside local/testing without --force.');

            return self::FAILURE;
        }

        $orderNumber = trim((string) $this->argument('order_number'));
        $order = BarOrder::where('order_number', $orderNumber)->first();
        if (! $order) {
            $this->error("Order not found: {$orderNumber}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$orderNumber} (id={$order->id}) permanently?", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();
        try {
            $kitchenItemIds = DB::table('kitchen_order_items')->where('order_id', $order->id)->pluck('id');

            if ($kitchenItemIds->isNotEmpty()) {
                DB::table('ingredient_stock_movements')
                    ->where('reference_type', KitchenOrderItem::class)
                    ->whereIn('reference_id', $kitchenItemIds)
                    ->delete();
            }

            DB::table('kitchen_order_item_extras')->whereIn('kitchen_order_item_id', $kitchenItemIds)->delete();
            DB::table('food_order_ingredients')->whereIn('kitchen_order_item_id', $kitchenItemIds)->delete();
            DB::table('kitchen_order_items')->where('order_id', $order->id)->delete();

            $orderItemIds = DB::table('order_items')->where('order_id', $order->id)->pluck('id');
            DB::table('transfer_sales')->whereIn('order_item_id', $orderItemIds)->delete();

            DB::table('order_payments')->where('order_id', $order->id)->delete();
            if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'order_id')) {
                DB::table('payments')->where('order_id', $order->id)->delete();
            }

            DB::table('stock_movements')
                ->where('reference_type', BarOrder::class)
                ->where('reference_id', $order->id)
                ->delete();

            DB::table('waiter_sms_notifications')->where('order_id', $order->id)->delete();

            DB::table('order_items')->where('order_id', $order->id)->delete();
            $order->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info("Deleted {$orderNumber} completely.");

        return self::SUCCESS;
    }
}
