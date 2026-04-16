<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$variants = ProductVariant::with('product')->get();

$file = fopen(__DIR__ . '/variant_list.csv', 'w');
fputcsv($file, ['ID', 'Product Name', 'Variant Name', 'Measurement', 'Package']);

foreach ($variants as $v) {
    fputcsv($file, [
        $v->id,
        $v->product->name ?? 'N/A',
        $v->name,
        $v->measurement,
        $v->packaging
    ]);
}
fclose($file);
echo "Exported " . count($variants) . " variants to scratch/variant_list.csv\n";
