<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$userList = [
    "M/Water Big" => 18,
    "M/Water Small" => 24,
    "Bavaria Chupa" => 6,
    "Red Bull Energy" => 48,
    "Heineken lager" => 48,
    "Corona" => 12,
    "Hennessy VSOP 750ml" => 3,
    "Hennessy VS 1L" => 4,
    "Hennessy VS 200ml" => 1,
    "Martell VSOP" => 2,
    "Martell VS" => 3,
    "J/walker-Black Label 750ml" => 2,
    "Jack Daniel's 1L" => 2,
    "Jack Daniel's 750ml" => 2,
    "Jack Daniel's 200ml" => 2,
    "Jack Daniel's Honey 750ml" => 1,
    "Ballantine's 750ml" => 1,
    "Black & White 200ml" => 2,
    "Jägermeister 200ml" => 2,
    "Absolut Vodka 1L" => 2,
    "Absolut Vodka 750ml" => 3,
    "Absolut Vodka 350ml" => 2,
    "Absolut Vodka 200ml" => 3,
    "Magic Moments Green Apple 750ml" => 1,
    "Magic Moments Chocolate 750ml" => 1,
    "Jameson 1L" => 3,
    "Jameson 750ml" => 1,
    "Jameson 375ml" => 1,
    "Jameson Black Barrel 750ml" => 1,
    "J & B 750ml" => 1,
    "Grants 750ml Glass" => 3,
    "Amarula 1ltr" => 1,
    "Amarula 750" => 1,
    "Gilbeys Gin 750" => 4,
    "Sminoff Vodika" => 5, // 250ml
    "Black label" => 1, // 200ml
    "Red Label" => 2, // 200ml
    "Chrome Gin vodika 750" => 2,
    "Chrome Gin smooth 200" => 2,
    "Campari 1ltr tot" => 3,
    "Famous Grouse 350ml" => 1,
    "Gordons 750ml" => 1,
    "Konyagi 200ml" => 16,
    "Konyagi 750ml" => 12,
    "Hanson Choice 750ml" => 6,
    "Highlife 750ml" => 11,
    "K-Vant 750ml" => 4,
    "K-Vant 200ml" => 14,
    "Martin Champagne 750ml" => 1,
    "Moët Nectar Imperial" => 1,
    "Four Cousins White 750ml" => 2,
    "Drostdy Hof Red Claret 375ml" => 4,
    "Drostdy Hof CRI White 700ml" => 4,
    "Drostdy Hof CRI White 375ml" => 2,
    "Pearly Bay Dry Red" => 1,
    "Pearly Bay Dry White" => 2,
    "Pearly Bay Sweet Red" => 2,
    "KWV Merlot" => 2,
    "Lions Hill Sweet Red" => 2,
    "Provetto Brut" => 2,
    "Nederburg Merlot 750ml" => 2,
    "Presidential noble" => 1,
    "TZEE lemon and ginger" => 1,
];

echo "--- MAPPING CHECK ---\n";
foreach ($userList as $name => $qty) {
    $v = ProductVariant::where('name', 'like', $name)->first();
    if ($v) {
        echo "MATCH: $name => ID: {$v->id} | Current Name: {$v->name} | Qty: $qty\n";
    } else {
        echo "MISSING: $name\n";
    }
}
