<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$variants = ProductVariant::where('name', 'like', '%Camino%')->get();

echo "--- CAMINO VARIANT DETAILS ---\n";
foreach($variants as $v) {
    echo "ID: {$v->id} | Name: {$v->name} | Unit: {$v->unit} | Items/Pkg: {$v->items_per_package} | Measurement: {$v->measurement}\n";
}
