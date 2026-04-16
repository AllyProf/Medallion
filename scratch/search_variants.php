<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$patterns = ['%Sminoff%', '%Pure%', '%Bonne%', '%Campari%', '%Heaven%', '%Smirnoff%'];

foreach ($patterns as $p) {
    echo "--- Search for $p ---\n";
    $variants = ProductVariant::where('name', 'like', $p)->get();
    foreach ($variants as $v) {
        echo "ID: {$v->id} | Name: {$v->name} | Measurement: {$v->measurement}\n";
    }
}
