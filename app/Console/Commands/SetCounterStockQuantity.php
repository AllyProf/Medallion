<?php

namespace App\Console\Commands;

use App\Models\StockLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetCounterStockQuantity extends Command
{
    protected $signature = 'stock:set-counter
                            {quantity : Target bottle/units count at the counter}
                            {--product= : Substring to match against products.name (e.g. Fanta)}
                            {--measurement= : Substring to match product_variants.measurement (e.g. 350)}
                            {--variant= : product_variants.id (skips name search)}
                            {--user= : Business owner user_id (defaults to first user with stock)}';

    protected $description = 'Set counter bar stock only (stock_locations.location=counter). Does not change warehouse/stock-keeper quantities.';

    public function handle(): int
    {
        $qty = max(0, (int) $this->argument('quantity'));
        $variantId = $this->option('variant');

        if ($variantId) {
            $pv = DB::table('product_variants')->where('id', (int) $variantId)->first();
            if (! $pv) {
                $this->error('product_variants id not found.');

                return self::FAILURE;
            }
        } else {
            $productNeedle = $this->option('product');
            if (! $productNeedle) {
                $this->error('Pass --product=... or --variant=id');

                return self::FAILURE;
            }

            $q = DB::table('products')->where('name', 'like', '%'.$productNeedle.'%');
            $productIds = $q->pluck('id');
            if ($productIds->isEmpty()) {
                $this->error('No products matching --product.');

                return self::FAILURE;
            }

            $vq = DB::table('product_variants')->whereIn('product_id', $productIds);
            $meas = $this->option('measurement');
            if ($meas) {
                $vq->where('measurement', 'like', '%'.$meas.'%');
            }
            $variants = $vq->get(['id', 'product_id', 'measurement', 'name']);

            if ($variants->isEmpty()) {
                $this->error('No variants match. Try a different --measurement or --product.');

                return self::FAILURE;
            }

            if ($variants->count() > 1) {
                $this->warn('Multiple variants matched:');
                foreach ($variants as $v) {
                    $this->line("  id={$v->id} measurement={$v->measurement} variant_name={$v->name}");
                }
                $this->error('Re-run with --variant=<id>.');

                return self::FAILURE;
            }

            $variantId = $variants->first()->id;
            $this->info("Using product_variants.id = {$variantId}");
        }

        $userId = $this->option('user');
        if (! $userId) {
            $userId = StockLocation::where('product_variant_id', $variantId)
                ->where('location', 'counter')
                ->value('user_id');
        }

        if (! $userId) {
            $userId = DB::table('products')->where(
                'id',
                DB::table('product_variants')->where('id', $variantId)->value('product_id')
            )->value('user_id');
        }

        if (! $userId) {
            $this->error('Could not resolve user_id. Pass --user=owner_id.');

            return self::FAILURE;
        }

        $loc = StockLocation::firstOrNew([
            'user_id' => $userId,
            'product_variant_id' => $variantId,
            'location' => 'counter',
        ]);

        $before = (int) $loc->quantity;
        $loc->quantity = $qty;
        $loc->save();

        $this->info('Counter stock updated (counter-stock page / location=counter only; warehouse unchanged).');
        $this->line("stock_locations id={$loc->id} user_id={$userId} product_variant_id={$variantId}: {$before} → {$qty}");

        return self::SUCCESS;
    }
}
