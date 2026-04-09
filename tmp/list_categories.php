<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cats = \App\Models\Product::pluck('category')->unique();
foreach ($cats as $c) {
    echo $c . "\n";
}
