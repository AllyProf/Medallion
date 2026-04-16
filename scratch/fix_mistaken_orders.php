<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarOrder;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

$orderNumbers = ['ORD-04', 'ORD-05', 'ORD-06'];

DB::beginTransaction();
try {
    foreach ($orderNumbers as $number) {
        $order = BarOrder::where('order_number', $number)->with('items')->first();
        if (!$order) {
            echo "Order $number not found.\n";
            continue;
        }

        if ($order->status === 'cancelled') {
            echo "Order $number is already cancelled.\n";
            continue;
        }

        echo "Processing $number (ID: {$order->id})...\n";

        foreach ($order->items as $item) {
            // Find counter stock
            $sl = StockLocation::where('user_id', $order->user_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->where('location', 'counter')
                ->first();

            if ($sl) {
                echo "  - Returning {$item->quantity} units of Variant ID: {$item->product_variant_id} to Counter Stock.\n";
                $sl->increment('quantity', $item->quantity);

                // Record Return Movement
                StockMovement::create([
                    'user_id' => $order->user_id,
                    'product_variant_id' => $item->product_variant_id,
                    'movement_type' => 'receipt', // Using receipt as "return to counter"
                    'from_location' => null,
                    'to_location' => 'counter',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'reference_type' => BarOrder::class,
                    'reference_id' => $order->id,
                    'notes' => "Manual return of mistaken order: $number",
                    'created_by' => $order->user_id,
                ]);
            } else {
                echo "  - ERROR: Counter stock record not found for Variant ID: {$item->product_variant_id}!\n";
            }
        }

        // Mark order as cancelled
        $order->status = 'cancelled';
        $order->notes = ($order->notes ? $order->notes . ' | ' : '') . 'CANCELLED DUE TO MISTAKE - STOCK RETURNED MANUALLY';
        $order->save();
        echo "Order $number cancelled.\n";
    }

    DB::commit();
    echo "SUCCESS: All orders corrected and stock returned.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
