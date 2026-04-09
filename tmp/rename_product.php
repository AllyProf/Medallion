<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = \App\Models\Product::find(1);
if ($p) {
    echo "Old Name: " . $p->name . "\n";
    $p->name = 'Soft Drinks (Bonite)';
    $p->save();
    echo "New Name: " . $p->name . "\n";
} else {
    echo "Product ID 1 not found\n";
}
