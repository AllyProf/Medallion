<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$ownerId = 4;

// 1. Update products to have category = brand where brand is not empty
$products = Product::where('user_id', $ownerId)->get();

$updated = 0;
foreach ($products as $p) {
    if (!empty($p->brand)) {
        // Prefer Title Case for better UI appearance rather than FULL CAPS
        $newCat = ucwords(strtolower($p->brand));
        
        // Special casing for acronyms if needed
        $newCat = str_replace('Gin & Local Spirits', 'GIN & Local Spirits', $newCat);
        
        if ($p->category !== $newCat) {
            $p->category = $newCat;
            $p->save();
            $updated++;
        }
    } else {
        // Map empty brand ones to the new standard categories
        $newCat = $p->category;
        switch ($p->category) {
            case 'Energies':
                $newCat = 'Energizers';
                break;
            case 'Beers/Lager':
                $newCat = 'Lager Beer (Bottles)';
                break;
            case 'Wines':
                $newCat = 'Wine & Champagne';
                break;
        }
        
        if ($p->category !== $newCat) {
            $p->category = $newCat;
            $p->save();
            $updated++;
        }
    }
}

echo "Successfully updated categories for $updated products to match Distributor Groups.\n";
