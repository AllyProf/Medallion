<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$queries = [
    '%Vodka%', '%Vodika%', '%Smirnoff%', '%Sminoff%', 
    '%Heaven%', '%Pure%', '%Bonne%', '%Esperance%', 
    '%Esprance%', '%Martell%', '%Hennesy%', '%Hennessy%',
    '%Campari%', '%Jameson%', '%Jamason%', '%Black label%',
    '%Blacklabel%'
];

foreach ($queries as $q) {
    echo "--- Query: $q ---\n";
    $variants = ProductVariant::where('name', 'like', $q)->get();
    foreach ($variants as $v) {
        echo "ID: {$v->id} | Name: {$v->name} | Measurement: {$v->measurement}\n";
    }
}
