<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

echo "Starting product name cleanup...\n";

$products = Product::whereNotNull('brand')->get();
$count = 0;

foreach ($products as $p) {
    $brand = trim($p->brand);
    $name = trim($p->name);
    
    // If name is identical to brand, fix it.
    if ($brand === $name && $p->variants()->count() > 0) {
        $vName = $p->variants()->first()->name;
        if ($vName !== $name) {
            echo "Fixing product id {$p->id}: Brand was '{$brand}', Name was '{$name}'. Setting name to variant: '{$vName}'\n";
            $p->name = $vName;
            $p->save();
            $count++;
        }
    }
}

echo "Cleanup complete. {$count} products updated.\n";
