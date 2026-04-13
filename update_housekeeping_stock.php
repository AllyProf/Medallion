<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Use the first user as the owner
$user = User::first();
if (!$user) {
    die("No user found in the database. Please ensure the system is set up.\n");
}

$data = [
    ['name' => 'Shampoo', 'quantity' => 153, 'cat' => 'housekeeping'],
    ['name' => 'Showergel', 'quantity' => 120, 'cat' => 'housekeeping'],
    ['name' => 'Slippers', 'quantity' => 152, 'cat' => 'housekeeping'],
    ['name' => 'Shower Cap', 'quantity' => 209, 'cat' => 'housekeeping'],
    ['name' => 'Sabuni (Soap)', 'quantity' => 193, 'cat' => 'housekeeping'],
    ['name' => 'Mosquito Repellent', 'quantity' => 4, 'cat' => 'housekeeping'],
    ['name' => 'Glass Cleaner', 'quantity' => 2, 'cat' => 'housekeeping'],
    ['name' => 'Air Freshener', 'quantity' => 7, 'cat' => 'housekeeping'],
    ['name' => 'Aro', 'quantity' => 2, 'cat' => 'housekeeping'],
    ['name' => 'Jamaa Bar Soap', 'quantity' => 1.5, 'cat' => 'housekeeping'],
    ['name' => 'Milk (Maziwa)', 'quantity' => 222, 'cat' => 'pantry'],
    ['name' => 'Coffee (Kahawa)', 'quantity' => 249, 'cat' => 'pantry'],
    ['name' => 'Tea Leaves (Majani)', 'quantity' => 627, 'cat' => 'pantry'],
    ['name' => 'Sugar (Sukari)', 'quantity' => 223, 'cat' => 'pantry'],
];

echo "Starting Housekeeping Stock Update...\n";
echo "------------------------------------\n";

DB::beginTransaction();

try {
    foreach ($data as $item) {
        // 1. Find or create Product
        $product = Product::where('name', $item['name'])->first();
        if (!$product) {
            $product = Product::create([
                'user_id' => $user->id,
                'name' => $item['name'],
                'category' => $item['cat'],
                'is_active' => true,
            ]);
            echo "Created new product: {$item['name']}\n";
        } else {
            // Update category if needed
            $product->category = $item['cat'];
            $product->save();
        }

        // 2. Find or create Variant (default to Piece)
        $variant = $product->variants()->first();
        if (!$variant) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'name' => 'Standard',
                'measurement' => 'Piece',
                'packaging' => 'Piece',
                'items_per_package' => 1,
                'buying_price_per_unit' => 0,
                'selling_price_per_unit' => 0,
                'unit' => 'pcs',
                'is_active' => true,
                'selling_type' => 'retail',
            ]);
            echo "Created standard variant for: {$item['name']}\n";
        }

        // 3. Upsert Stock Location
        StockLocation::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_variant_id' => $variant->id,
                'location' => 'warehouse',
            ],
            [
                'quantity' => $item['quantity'],
            ]
        );

        echo "Updated stock for [{$item['name']}] -> Quantity: {$item['quantity']}\n";
    }

    DB::commit();
    echo "------------------------------------\n";
    echo "Stock update completed successfully!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
