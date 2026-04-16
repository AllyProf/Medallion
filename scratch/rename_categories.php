<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$ownerId = 4;

$mappings = [
    'Water' => 'Soda & Water',
    'Beers' => 'Beers/Lager',
    'Soft Drinks' => 'Soft Drinks & Juices'
];

echo "--- STARTING CATEGORY RENAMING ---\n";

foreach ($mappings as $old => $new) {
    $count = Product::where('user_id', $ownerId)
        ->where('category', $old)
        ->update(['category' => $new]);
    
    echo "Updated '{$old}' to '{$new}': {$count} products affected.\n";
}

echo "--- FINISHED ---\n";
