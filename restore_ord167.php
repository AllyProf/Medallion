<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    DB::beginTransaction();
    
    // Check if it already exists
    if (\App\Models\BarOrder::where('order_number', 'ORD-167')->exists()) {
        echo "ORD-167 already exists. Skipping.\n";
        DB::rollBack();
        exit;
    }

    $order = \App\Models\BarOrder::create([
        'user_id' => 4,
        'order_number' => 'ORD-167',
        'status' => 'pending',
        'payment_status' => 'pending',
        'total_amount' => 5000,
        'paid_amount' => 0,
        'waiter_id' => 37,
        'bar_shift_id' => 6,
        'order_source' => 'kiosk',
        'created_at' => '2026-04-19 09:09:29',
        'updated_at' => '2026-04-19 09:09:29'
    ]);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'product_variant_id' => 24,
        'product_name' => 'Red Bull Energy (250 ml)',
        'quantity' => 1,
        'unit_price' => 5000,
        'total_price' => 5000,
        'created_at' => '2026-04-19 09:09:29',
        'updated_at' => '2026-04-19 09:09:29'
    ]);
    
    echo "SUCCESS: Restored ORD-167 and its items to Shift #6.\n";
    DB::commit();

} catch (\Exception $e) {
    echo "ERROR during restoration: " . $e->getMessage() . "\n";
    DB::rollBack();
}
