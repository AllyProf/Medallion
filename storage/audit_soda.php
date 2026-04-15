<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$variantId = 26;
$ownerId = 4;

$wh = \App\Models\StockLocation::where('product_variant_id', $variantId)
    ->where('location', 'warehouse')
    ->where('user_id', $ownerId)
    ->first();

echo "Before: {$wh->quantity}\n";
$wh->update(['quantity' => 335]);
echo "After:  " . $wh->fresh()->quantity . "\n";
echo "Corrected Bonite Soda warehouse stock from 335.80 → 335.00\n";
