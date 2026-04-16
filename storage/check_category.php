<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$products = \App\Models\Product::where('name', 'like', '%Bonite Soda%')->get();

foreach ($products as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Category: {$p->category}\n";
}
