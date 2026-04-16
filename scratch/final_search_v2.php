<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$searchItems = ['Pure', 'Heaven', 'Bonne', 'Esperance', 'Esprance', 'Smirnoff', 'Sminoff', 'Vodka', 'Vodika'];

foreach ($searchItems as $s) {
    echo "--- Search: $s ---\n";
    $v = ProductVariant::where('name', 'like', "%$s%")->get();
    foreach ($v as $item) {
        echo "ID: {$item->id} | Name: {$item->name} | Product: " . ($item->product->name ?? 'N/A') . "\n";
    }
}
