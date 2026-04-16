<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$queries = [
    'pure' => '%Pure%',
    'heaven' => '%Heaven%',
    'bonne' => '%Bonne%',
    'esperance' => '%Esperance%',
    'smirnoff' => '%Smirnoff%',
    'vodka' => '%Vodka%',
    'sminoff' => '%Sminoff%',
    'campari' => '%Campari%',
    'hennessy' => '%Hennessy%',
    'hennesy' => '%Hennesy%',
    'red label' => '%Red Label%',
    'black label' => '%Black Label%',
    'jack' => '%Jack%',
    'walker' => '%Walker%'
];

foreach ($queries as $label => $pattern) {
    echo "--- $label ($pattern) ---\n";
    $variants = ProductVariant::where('name', 'like', $pattern)->get();
    foreach ($variants as $v) {
        echo "ID: {$v->id} | Name: {$v->name} | Measurement: {$v->measurement}\n";
    }
}
