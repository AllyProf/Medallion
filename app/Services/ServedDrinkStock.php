<?php

namespace App\Services;

use App\Models\BarOrder;
use App\Models\OpenBottle;
use App\Models\OrderItem;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransferSale;

class ServedDrinkStock
{
    public function take(BarOrder $order, OrderItem $item, int $extraQty, string $by = 'counter'): void
    {
        if ($extraQty <= 0 || ! $item->product_variant_id) {
            return;
        }

        $item->loadMissing('productVariant');
        if (($item->sell_type ?? 'unit') === 'tot') {
            $this->takeTots($order, $item, $extraQty, $by);
        } else {
            $this->takeUnits($order, $item, $extraQty, $by);
        }
    }

    public function putBack(BarOrder $order, OrderItem $item, int $returnQty): void
    {
        if ($returnQty <= 0 || ! $item->product_variant_id) {
            return;
        }

        $item->loadMissing('productVariant');
        if (($item->sell_type ?? 'unit') === 'tot') {
            $this->returnTots($order, $item, $returnQty);
        } else {
            $this->returnUnits($order, $item, $returnQty);
        }

        $bottleQty = (float) $returnQty;
        if (($item->sell_type ?? 'unit') === 'tot') {
            $perBottle = (int) ($item->productVariant->total_tots ?? 0);
            $bottleQty = $perBottle > 0 ? $returnQty / $perBottle : $returnQty;
        }
        $this->reduceTransferSales($item, $bottleQty);
    }

    private function takeUnits(BarOrder $order, OrderItem $item, int $extraQty, string $by): void
    {
        $name = $item->productVariant->display_name ?? 'Drink';
        $counterStock = StockLocation::where('user_id', $order->user_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('location', 'counter')
            ->first();
        $available = $counterStock ? (float) $counterStock->quantity : 0;
        if (! $counterStock || $available < $extraQty) {
            throw new \InvalidArgumentException($name.' only has '.(int) $available.' left at the counter.');
        }

        $counterStock->decrement('quantity', $extraQty);
        app(StockAlertService::class)->checkCounterStock($item->product_variant_id, $order->user_id);
        StockMovement::create([
            'user_id' => $order->user_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => 'sale',
            'from_location' => 'counter',
            'to_location' => null,
            'quantity' => $extraQty,
            'unit_price' => $item->unit_price,
            'reference_type' => BarOrder::class,
            'reference_id' => $order->id,
            'created_by' => $order->user_id,
            'notes' => 'Quantity increased at '.$by.': '.$order->order_number,
        ]);
    }

    private function takeTots(BarOrder $order, OrderItem $item, int $extraQty, string $by): void
    {
        $name = $item->productVariant->display_name ?? 'Drink';
        $perBottle = max(1, (int) ($item->productVariant->total_tots ?? 1));
        $totsNeeded = $extraQty;
        $counterStock = StockLocation::where('user_id', $order->user_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('location', 'counter')
            ->lockForUpdate()
            ->first();

        $openBottle = OpenBottle::where('user_id', $order->user_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->lockForUpdate()
            ->first();
        if ($openBottle) {
            $fromOpen = min((int) $openBottle->tots_remaining, $totsNeeded);
            $openBottle->decrement('tots_remaining', $fromOpen);
            $totsNeeded -= $fromOpen;
            if ($openBottle->tots_remaining <= 0) {
                $openBottle->delete();
            }
        }

        while ($totsNeeded > 0 && $counterStock && $counterStock->quantity >= 1) {
            $counterStock->decrement('quantity', 1);
            $counterStock->refresh();
            if ($totsNeeded >= $perBottle) {
                $totsNeeded -= $perBottle;
            } else {
                OpenBottle::create([
                    'user_id' => $order->user_id,
                    'product_variant_id' => $item->product_variant_id,
                    'tots_remaining' => $perBottle - $totsNeeded,
                ]);
                $totsNeeded = 0;
            }
            StockMovement::create([
                'user_id' => $order->user_id,
                'product_variant_id' => $item->product_variant_id,
                'movement_type' => 'sale',
                'from_location' => 'counter',
                'to_location' => null,
                'quantity' => 1,
                'unit_price' => $item->unit_price,
                'reference_type' => BarOrder::class,
                'reference_id' => $order->id,
                'created_by' => $order->user_id,
                'notes' => 'Quantity increased at '.$by.': '.$order->order_number,
            ]);
        }

        if ($totsNeeded > 0) {
            throw new \InvalidArgumentException($name.' does not have enough left at the counter.');
        }
    }

    private function returnUnits(BarOrder $order, OrderItem $item, int $returnQty): void
    {
        $movements = StockMovement::where('reference_type', BarOrder::class)
            ->where('reference_id', $order->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->whereIn('movement_type', ['sale', 'usage'])
            ->orderByDesc('id')
            ->get();

        $returnable = min($returnQty, (int) floor((float) $movements->sum('quantity')));
        if ($returnable <= 0) {
            if ($item->is_served || $order->status === 'served') {
                $counterStock = StockLocation::where('user_id', $order->user_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->where('location', 'counter')
                    ->first();
                if ($counterStock) {
                    $counterStock->increment('quantity', $returnQty);
                }
            }

            return;
        }

        $counterStock = StockLocation::where('user_id', $order->user_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('location', 'counter')
            ->first();
        if ($counterStock) {
            $counterStock->increment('quantity', $returnable);
        }

        $left = $returnable;
        foreach ($movements as $movement) {
            if ($left <= 0) {
                break;
            }
            $qty = (float) $movement->quantity;
            if ($qty <= $left + 0.0001) {
                $left -= (int) round($qty);
                $movement->delete();
            } else {
                $movement->quantity = $qty - $left;
                $movement->save();
                $left = 0;
            }
        }
    }

    private function returnTots(BarOrder $order, OrderItem $item, int $returnQty): void
    {
        $perBottle = max(1, (int) ($item->productVariant->total_tots ?? 1));
        $openBottle = OpenBottle::where('user_id', $order->user_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->first();
        if (! $openBottle) {
            $openBottle = OpenBottle::create([
                'user_id' => $order->user_id,
                'product_variant_id' => $item->product_variant_id,
                'tots_remaining' => 0,
            ]);
        }
        $openBottle->increment('tots_remaining', $returnQty);
        $openBottle->refresh();

        $counterStock = StockLocation::where('user_id', $order->user_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('location', 'counter')
            ->first();

        while ($openBottle->tots_remaining >= $perBottle) {
            $openBottle->decrement('tots_remaining', $perBottle);
            $openBottle->refresh();
            if ($counterStock) {
                $counterStock->increment('quantity', 1);
            }
            $movement = StockMovement::where('reference_type', BarOrder::class)
                ->where('reference_id', $order->id)
                ->where('product_variant_id', $item->product_variant_id)
                ->whereIn('movement_type', ['sale', 'usage'])
                ->orderByDesc('id')
                ->first();
            if ($movement) {
                if ((float) $movement->quantity <= 1) {
                    $movement->delete();
                } else {
                    $movement->quantity = (float) $movement->quantity - 1;
                    $movement->save();
                }
            }
        }

        if ($openBottle->tots_remaining <= 0) {
            $openBottle->delete();
        }
    }

    private function reduceTransferSales(OrderItem $item, float $returnQty): void
    {
        if ($returnQty <= 0) {
            return;
        }

        $sales = TransferSale::where('order_item_id', $item->id)->orderByDesc('id')->get();
        $left = $returnQty;
        foreach ($sales as $sale) {
            if ($left <= 0.0001) {
                break;
            }
            $take = min((float) $sale->quantity, $left);
            $newQty = (float) $sale->quantity - $take;
            if ($newQty <= 0.0001) {
                $sale->delete();
            } else {
                $sale->quantity = $newQty;
                $sale->total_price = $newQty * (float) $sale->unit_price;
                $sale->save();
            }
            $left -= $take;
        }
    }
}
