<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$categories = \App\Models\Product::where('user_id', 4)->distinct()->pluck('category');
echo "Categories found for user 4:\n";
foreach ($categories as $cat) {
    echo "- $cat\n";
}
